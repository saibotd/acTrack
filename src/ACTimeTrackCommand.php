<?php
namespace saibotd\actrack;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Helper\ProgressBar;
use ActiveCollab\SDK\Token;
use ActiveCollab\SDK\TokenInterface;
use ActiveCollab\SDK\Authenticator\Cloud;
use ActiveCollab\SDK\Authenticator\SelfHosted;
use ActiveCollab\SDK\Client;
use saibotd\actrack\TimeTrackerDiff;
use saibotd\actrack\TimeTrackerTick;

class ACTimeTrackCommand extends Command{
    private $authenticator, $token, $acClient, $timeTracker, $session, $tasks, $task, $project, $projects, $companies;

    public function __construct(){
        parent::__construct();
        $this->timeTracker = new TimeTrackerTick();
        if(!$this->timeTracker->isWorking())
            $this->timeTracker = new TimeTrackerDiff();
    }

    protected function configure(){
        $this->setName('actrack')
            ->setDescription('track time')
            ->addOption(
                'email',
                null,
                InputOption::VALUE_OPTIONAL,
                'Your email for login'
            )->addOption(
                'password',
                null,
                InputOption::VALUE_OPTIONAL,
                'Your password for login'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output){
        if(is_file(sys_get_temp_dir() . '/.actoken')){
            $actoken = json_decode(file_get_contents(sys_get_temp_dir() . '/.actoken'));
            $this->token = new \ActiveCollab\SDK\Token($actoken->token, $actoken->url);
            $output->writeln("Found token, logging you back in");
            $this->acClient = new Client($this->token);
        } else {
            $this->login($input, $output);
            $this->selectInstance($input, $output);
        }
        $this->loadInfo($input, $output);
        $this->selectTask($input, $output);
    }

    function login(InputInterface $input, OutputInterface $output){
        $helper = $this->getHelper('question');
        if($input->getOption('email') && $input->getOption('password')){
            $email = $input->getOption('email');
            $password = $input->getOption('password');
        } else {
            $question = new Question('Please enter your email address: ');
            $email = $helper->ask($input, $output, $question);
            $question = new Question('Please enter your password: ');
            $question->setHidden(true);
            $password = $helper->ask($input, $output, $question);
        }
        $this->authenticator = new Cloud('saibotd', 'acClient', $email, $password);
        $output->writeln("Login successful, fetching ActiveCollab instances");
    }

    function workingOnTitle(){
        if($this->task) return $this->project['name'] . ' > ' . $this->task['name'];
        else return $this->project['name'];
    }

    function selectInstance(InputInterface $input, OutputInterface $output){
        $helper = $this->getHelper('question');
        $instances = array_values($this->authenticator->getAccounts());

        $titles = [];
        foreach ($instances as $key => $value) {
            $titles[] = $value['name'];
        }

        $question = new ChoiceQuestion(
            '<question>Select your instance</question>',
            $titles,
            0
        );

        $index = array_search($helper->ask($input, $output, $question), $titles);
        $this->token = $this->authenticator->issueToken($instances[$index]['id']);
        if ($this->token instanceof TokenInterface) {
            file_put_contents(sys_get_temp_dir() . '/.actoken', json_encode([
                'token' => $this->token->getToken(),
                'url' => $this->token->getUrl()
            ]));
        } else {
            die("Could not get valid token");
        }
        $this->acClient = new Client($this->token);

        $output->writeln("Connected to " . $instances[$index]['name']);
    }

    function loadInfo(InputInterface $input, OutputInterface $output){
        $this->session = $this->acClient->get('user-session')->getJson();
        $this->companies = $this->acClient->get('companies/all')->getJson();
    }

    function selectTask(InputInterface $input, OutputInterface $output){
        $this->tasks = $this->acClient->get('users/'.$this->session['logged_user_id'].'/tasks')->getJson();
        $this->project = null;
        $this->task = null;
        $helper = $this->getHelper('question');

        $titles = [];
        foreach($this->tasks['tasks'] as $task){
            $project = $this->tasks['related']['Project'][$task['project_id']];
            $title = $project['name'] . " > " . $task['name'];
            if(!$project['is_tracking_enabled']) $title .= ' <error>!tracking</error>';
            $titles[] = $title;
        }
        $titles[] = "<info>Project list</info>";
        $question = new ChoiceQuestion(
            '<question>Select the task you are working on</question>',
            $titles
        );

        $index = array_search($helper->ask($input, $output, $question), $titles);
        if($index != count($titles)-1){
            $task = $this->tasks['tasks'][$index];
            $project = $this->tasks['related']['Project'][$task['project_id']];
            if($project['is_tracking_enabled']){
                $this->task = $task;
                $this->project = $project;

                $this->timeTracker->start();
                $this->trackingIdle($input, $output);
            } else {
                $output->writeln("<error>Please enable time tracking for this job first</error>");
                $this->selectTask($input, $output);
            }
        } else {
            $this->selectProject($input, $output);
        }

    }

    function selectProject(InputInterface $input, OutputInterface $output){
        $this->projects = $this->acClient->get('projects')->getJson();
        $this->project = null;
        $this->task = null;
        $helper = $this->getHelper('question');

        $titles = [];
        foreach($this->projects as $project){
            $title = $project['name'];
            if(!$project['is_tracking_enabled']) $title .= ' <error>!tracking</error>';
            $titles[] = $title;
        }
        $titles[] = "<info>Task list</info>";
        $question = new ChoiceQuestion(
            '<question>Select the project you are working on</question>',
            $titles
        );

        $index = array_search($helper->ask($input, $output, $question), $titles);
        if($index != count($titles)-1){
            if($this->projects[$index]['is_tracking_enabled']){
                $this->project = $this->projects[$index];
                $this->timeTracker->start();
                $this->trackingIdle($input, $output, $this->acClient);
            } else {
                $output->writeln("<error>Please enable time tracking for this job first</error>");
                $this->selectProject($input, $output);
            }
        } else {
            $this->selectTask($input, $output);
        }

    }

    function trackingIdle(InputInterface $input, OutputInterface $output){
        $helper = $this->getHelper('question');

        $options = ["Refresh", "Done", "Abort"];
        if($this->project && $this->timeTracker->isPaused()){
            $options[] = "Continue";
            $title = '<info>Tracking is PAUSED for "' . $this->workingOnTitle() . '" (' . Helper::format_time($this->timeTracker->getSeconds()) . ')</info>';
        } else {
            $options[] = "Pause";
            $title = '<info>Tracking is ACTIVE for "' . $this->workingOnTitle() . '" (' . Helper::format_time($this->timeTracker->getSeconds()) . ')</info>';
        }

        $question = new ChoiceQuestion($title, $options, 0);

        switch($helper->ask($input, $output, $question)){
            case "Refresh":
                $this->trackingIdle($input, $output);
            break;
            case "Done":
                $trackedSeconds = $this->timeTracker->stop();
                $this->submitTracking($input, $output, $trackedSeconds);
            break;
            case "Pause":
                $this->timeTracker->pause();
                $this->trackingIdle($input, $output);
            break;
            case "Continue":
                $this->timeTracker->start();
                $this->trackingIdle($input, $output);
            break;
            case "Abort":
                $this->timeTracker->stop();
                $this->selectTask($input, $output);
            break;
        }
    }

    function submitTracking(InputInterface $input, OutputInterface $output, $trackedSeconds){

        $helper = $this->getHelper('question');

        $hours = number_format($trackedSeconds/60/60, 2);
        $question = new Question('Hours worked ['.$hours .']: ', $hours );
        $hours = $helper->ask($input, $output, $question);

        $titles = [];
        $default = 0;
        foreach($this->session['job_types'] as $index => $type){
            if($type->is_default){
                $default = $index;
                $titles[] = $type['name'] . "*";
            } else
                $titles[] = $type['name'];
        }
        $question = new ChoiceQuestion(
            '<question>Select the type of job</question>',
            $titles, $default
        );

        $jobType = $helper->ask($input, $output, $question);
        $jobTypeID = $this->session['job_types'][array_search($jobType, $titles)]['id'];

        $question = new Question('Summary: ');
        $summary = $helper->ask($input, $output, $question);

        $question = new ConfirmationQuestion('Billable? <info>[Y/n]</info>: ', true);
        $billable = $helper->ask($input, $output, $question);

        $questionText = "<question>You are about to submit $hours";
        if($billable) $questionText .=  ' billable';
        $questionText .=  " hours of the type \"$jobType\" on \""  . $this->workingOnTitle() . '". Is all of this correct?</question> <info>[Y/n]</info>: ' ;

        $question = new ConfirmationQuestion($questionText, true);

        if($helper->ask($input, $output, $question)){
            $task_id = $this->task ? $this->task['id'] : null;
            $res = $this->acClient->post('projects/'.$this->project['id'].'/time-records', [
                "value" => $hours,
                "job_type_id" => $jobTypeID,
                "user_id" => $this->session['logged_user_id'],
                "record_date" => date('Y-m-d'),
                "billable_status" => $billable,
                "summary" => $summary,
                "task_id" => $task_id
            ]);
            $this->selectTask($input, $output);
        } else {
            $this->timeTracker->setSeconds($trackedSeconds);
            $this->trackingIdle($input, $output);
        }
    }
}
