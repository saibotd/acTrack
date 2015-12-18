<?php
unlink('.seconds');
for(;;){
    if(file_exists('.seconds'))
        file_put_contents('.seconds', intval(file_get_contents('.seconds')) + 1);
    sleep(1);
}