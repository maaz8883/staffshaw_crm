<?php 
function validate($name,$field,$validation){
    global $error;
    $error_msg = false;
    $value = !empty($_POST[$field]) ? $_POST[$field] : '';

    if(empty($name) || empty($field)){
        throw new Exception('Name and Field are required for validation');
    }

    $validations = explode('|',$validation);

    if(is_array($value)){
        foreach($value as $key => $val){
            $res[$key] = htmlspecialchars($val);  
        }  
        $value = $res;
    }
    else{
        $value = htmlspecialchars($value);    
    }

    if(in_array('required',$validations)){
        if (empty($value)) {
            $error_msg = "$name is a required Field";
        }        
    }

    if(!empty($value) && in_array('name',$validations)){
        if (!preg_match("/^[a-zA-Z-' ]*$/",$name)) {
            $error_msg = "Only letters and white space allowed for $name";
        }
    }

    if(!empty($value) && in_array('email',$validations)){
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $error_msg = "Invalid $name";
        }
        else {
            $value = filter_var($value, FILTER_SANITIZE_EMAIL);
        }
    }

    if(!empty($value) && in_array('url',$validations)){
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $error_msg = "Invalid $name";
        }
        else {
            $value = filter_var($value, FILTER_SANITIZE_URL);
        }
    }

    if(!empty($value) && in_array('phone.us',$validations)){
        if(!preg_match("/^[a-zA-Z-' ]*$/",$name)) {            
            $error_msg = "Invalid $name Pattern";
        }
    }

    if($error_msg){
        global $error_prefex;
        global $error_sufex;
        $error[$field] = $error_prefex.$error_msg.$error_sufex;
        return $value;
    }
    else{
        return $value;
    }
}