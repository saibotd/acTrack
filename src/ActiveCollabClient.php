<?php
namespace saibotd\acTrack;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

class ActiveCollabClient{
    public $userID;
    private $client, $instance, $defaultOpts;
    private $loggedIn = false;

    public function __construct(){
        $this->client = new Client([
            'base_uri' => 'https://app.activecollab.com/',
            'timeout'  => 20.0,
            'cookies' => true,
            'http_errors' => true,
            'headers' => [
                'user-agent' => 'saibotd/active.collab',
                'content-type' => 'application/json;charset=UTF-8'
            ]
        ]);
    }

    public function login($email, $password){
        $error = false;
        try{
            $res = $this->client->request('POST', 'https://my.activecollab.com/api/v1/login', [
                'json' => [
                    "login" => [
                        "email" => $email,
                        "password" => $password,
                        "remember" => true
                    ],
                    "submitted" => "submitted"
                ]
            ]);
        } catch(RequestException $e){
            $error = $e;
        } catch (ClientException $e) {
            $error = $e;
        }
        if($error){
            if ($error->hasResponse()) {
                $res = $error->getResponse();
                $data = json_decode($res->getBody());
                if(isset($data->message)){
                    throw new \Exception($data->message);
                }
            }
        }
        $this->loggedIn = true;
        return json_encode($res->getBody());
    }

    public function fetchCloudInstances(){
        $res = $this->client->request('GET', 'https://my.activecollab.com/api/v1/products/cloud-instances');
        return json_decode($res->getBody());
    }

    public function fetchSelfHostedInstances(){
        $res = $this->client->request('GET', 'https://my.activecollab.com/api/v1/products/self-hosted');
        return json_decode($res->getBody());
    }

    public function setInstance($instance){
        $this->instance = $instance;
        $this->base_uri = substr($instance->urls->view, 0, strpos($instance->urls->view, '/acid-auth'));
    }

    public function getInstance(){
        return $this->instance;
    }

    public function connectInstance(){
        $res = $this->client->request('GET', $this->instance->urls->view, ['allow_redirects' => false]);
        $setCookies = $res->getHeader('set-cookie');
        foreach($setCookies as $line){
            if(stripos($line, "csrf_validator")){
                $csrf = substr($line, strpos($line, "=") + 1);
                $csrf = substr($csrf, 0, strpos($csrf, ";"));
            }
        }
        $this->defaultOpts = ['headers' => ['x-angie-csrfvalidator' => $csrf]];
        return true;
    }
    private function fetch($path, $method = "GET", $data = null){
        $error = false;
        $opts = $this->defaultOpts;
        if($data) $opts["json"] = $data;
        try{
            $res = $this->client->request($method, "$this->base_uri/$path", $opts);
        } catch(RequestException $e){
            $error = $e;
        } catch (ClientException $e) {
            $error = $e;
        }
        if($error){
            if ($error->hasResponse()) {
                $res = $error->getResponse();
                $data = json_decode($res->getBody());
                if(isset($data->message)){
                    throw new Exception($data->message);
                }
            }
        }
        return json_decode($res->getBody());
    }
    public function fetchSession(){
        $session = $this->fetch('user-session');
        $this->userID = $session->logged_user_id;
        return $session;
    }
    public function fetchTasks(){
        return $this->fetch("users/$this->userID/tasks");
    }
    public function fetchProjects(){
        return $this->fetch('projects');
    }
    public function fetchCompanies(){
        return $this->fetch('companies/all');
    }
    public function submitTimeRecord($hours, $jobTypeID, $recordDate, $billableStatus, $projectID, $taskID, $summary = ""){
        $data = [
            "value" => $hours,
            "job_type_id" => $jobTypeID,
            "user_id" => $this->userID,
            "record_date" => $recordDate,
            "billable_status" => $billableStatus,
            "summary" => $summary,
            "task_id" => $taskID
        ];
        $this->fetch("projects/$projectID/time-records", 'POST', $data);
    }
}