<?php

if($_SERVER["REQUEST_METHOD"] == "POST"){
	$error = array();
	require 'validate.php';
	if(!empty($_POST['cta1'])){		
		$name = validate('Name','name', 'required|name');
		$email = validate('Email','email','required|email');
		$phone = validate('Phone','phone','required|phone.us');
		$message = validate('Message','message','');
		$source= validate('Source','source','');





		$url = validate('Url','url', '');
		$domain = validate('Domain','domain','');
		$subject = validate('Subject','subject','');
		$ip = validate('Ip','ip','');
		$city = validate('City','city','');
		$region = validate('Region','region','');
		$country = validate('Country','country','');
// 		$price = validate('Price','price','');
// 		$pkgctgry = validate('Pkgctgry','pkgctgry','');
// 		$pkgtitle = validate('Pkgtitle','pkgtitle','');
		// $service = validate('Service','service','');
		// Assuming you're submitting a form with 'name' and 'email' fields
    
	
		if(empty($error)){
			$error['form'] = 'cta1';
			require 'mail.php';
			ob_start();
			include ($cta_template);
			$cta_template = ob_get_clean();
			$res = send_mail($cta_email, $cta_subject, $cta_template);
			
			if($res){
				
				header('Location:/thankyou');
          		die();
			}
			else{
				throw new Exception('Email sending failed.');
			}
		}
		else {
		    echo '<pre>'; print_r($error); exit;
		}
	}
	elseif(!empty($_POST['cta2'])){	
        $url = validate('Url','url', '');
		$domain = validate('Domain','domain','');
		$subject = validate('Subject','subject','');
		$ip = validate('Ip','ip','');
		$city = validate('City','city','');
		$region = validate('Region','region','');
		$country = validate('Country','country','');	
		$email = validate('Email','email','required|email');
		if(empty($error)){
			$error['form'] = 'cta2';
			require 'mail.php';
			ob_start();
			include ($cta2_template);
			$cta2_template = ob_get_clean();
			$res = send_mail($cta_email, $cta_subject, $cta2_template);
			if($res){
				header('Location:/thankyou');
          		die();
			}
			else{
				throw new Exception('Email sending failed.');
			}
		}			
	}
	elseif(!empty($_POST['cta3'])){	
		$manuscript_ready = validate('manuscript_ready','manuscript-ready','');
		$plan_on_publishing = validate('plan_on_publishing','plan-on-publishing','');
		$published_before = validate('published_before','published-before','');
		$services = validate('services','services','');
        $name = validate('Name','name', 'required|name');
		$email = validate('Email','email','required|email');
		$phone = validate('Phone','phone','required|phone.us');

		$url = validate('Url','url', '');
		$domain = validate('Domain','domain','');
		$subject = validate('Subject','subject','');
		$ip = validate('Ip','ip','');
		$city = validate('City','city','');
		$region = validate('Region','region','');
		$country = validate('Country','country','');
		if(empty($error)){
			$error['form'] = 'cta3';
			require 'mail.php';
			ob_start();
			include ($cta3_template);
			$cta3_template = ob_get_clean();
			$res = send_mail($cta_email, $cta_subject, $cta3_template);
			if($res){
				header('Location:/thankyou');
          		die();
			}
			else{
				throw new Exception('Email sending failed.');
			}
		}			
	}
    elseif(!empty($_POST['cta4'])){	
		$budget = validate('budget','budget','');
		$genere = validate('genere','genere','');
        $name = validate('Name','name', 'required|name');
		$email = validate('Email','email','required|email');
		$phone = validate('Phone','phone','required|phone.us');

		$url = validate('Url','url', '');
		$domain = validate('Domain','domain','');
		$subject = validate('Subject','subject','');
		$ip = validate('Ip','ip','');
		$city = validate('City','city','');
		$region = validate('Region','region','');
		$country = validate('Country','country','');
		if(empty($error)){
			$error['form'] = 'cta4';
			require 'mail.php';
			ob_start();
			include ($cta4_template);
			$cta4_template = ob_get_clean();
			$res = send_mail($cta_email, $cta_subject, $cta4_template);
			if($res){
				header('Location:/thankyou');
          		die();
			}
			else{
				throw new Exception('Email sending failed.');
			}
		}			
	}
	elseif(!empty($_POST['cta5'])){	
		$duration = validate('duration','duration','');
		$published = validate('published','published','');
        $name = validate('Name','name', 'required|name');
		$email = validate('Email','email','required|email');
		$phone = validate('Phone','phone','required|phone.us');
		$source= validate('Source','source','');
		$url = validate('Url','url', '');
		$domain = validate('Domain','domain','');
		$subject = validate('Subject','subject','');
		$ip = validate('Ip','ip','');
		$city = validate('City','city','');
		$region = validate('Region','region','');
		$country = validate('Country','country','');
		if(empty($error)){
			$error['form'] = 'cta3';
			require 'mail.php';
			ob_start();
			include ($cta5_template);
			$cta5_template = ob_get_clean();
			$res = send_mail($cta_email, $cta_subject, $cta5_template);
			if($res){
				header('Location:/thankyou');
          		die();
			}
			else{
				throw new Exception('Email sending failed.');
			}
		}			
	}
	elseif(!empty($_POST['contact'])){		
		$name = validate('Name','name', 'required|name');
		$email = validate('Email','email','required|email');
		$phone = validate('Phone','phone','required|phone.us');
		$message = validate('Message','message','');
		$service = validate('Service','service','');
		if(empty($error)){
			$error['form'] = 'cta2';
			require 'mail.php';
			ob_start();
			include ($cta_template);
			$cta_template = ob_get_clean();
			$res = send_mail($cta_email, $cta_subject, $cta_template);
			if($res){
				header('Location:'. $domainname.'/thankyou');
          		die();
			}
			else{
				throw new Exception('Email sending failed.');
			}
		}			
	}
	elseif(!empty($_POST['webiste-brief'])){
		$name = validate('Name','name', 'required|name');
		$email = validate('Email','email','required|email');
		$business_name = validate('Business Name','business_name','required');
		$purpose = validate('Purpose','purpose','required');
		$navigation = validate('Navigation','navigation','required');
		$feeling = validate('Feeling','feeling','required');
		$description = validate('Description','description','required');
		$audience = validate('Audience','audience','');
		$design_specification = validate('Design_specification','design_specification','required');
		$comments = validate('Comments','comments','');
		$domain = validate('Domain','domain','required');
		$hosting = validate('Hosting','hosting','required');
		if(empty($error)){
			$error['form'] = 'webiste-brief';		
			require 'mail.php';
			ob_start();
			include ($webbrief_template);
			$webbrief_template = ob_get_clean();
			$res = send_mail($brief_email, $webbrief_subject, $webbrief_template);
			if($res){
				header('Location:'. $domainname.'/thankyou');          		
          		die();
			}
			else{
				throw new Exception('Email sending failed.');
			}
		}	
	}
	elseif(!empty($_POST['seo-brief'])){
		$company_name = validate('Company_name','company_name', 'required|company_name');
		$contact_pname = validate('Contact_pname','contact_pname','required|contact_pname');
		$email = validate('Email','email','required|email');
		$company_website = validate('Company_website','company_website','required');
		$description = validate('Description','description','required');
		$target_country = validate('Target_country','target_country','required');
		$audience = validate('Audience','audience','required');
		$goals = validate('Goals','goals','required');
		$presence = validate('Presence','presence','required');
		$competitor = validate('Competitor','competitor','');
		$budget = validate('Budget','budget','required');
		$keywords = validate('Keywords','keywords','required');
		$preferences = validate('Preferences','preferences','');
		$analytics = validate('Analytics','analytics','required');
		$timeline = validate('Timeline','timeline','required');
		$comments = validate('Comments','comments','');
		if(empty($error)){
			$error['form'] = 'seo-brief';		
			require 'mail.php';
			ob_start();
			include ($seobrief_template);
			$seobrief_template = ob_get_clean();
			$res = send_mail($brief_email, $seobrief_subject, $seobrief_template);
			if($res){
				header('Location:'. $domainname.'/thankyou');          		
          		die();
			}
			else{
				throw new Exception('Email sending failed.');
			}
		}	
	}
	elseif(!empty($_POST['smm-brief'])){
		$company_name = validate('Company_name','company_name', 'required|company_name');
		$contact_pname = validate('Contact_pname','contact_pname','required|contact_pname');
		$email = validate('Email','email','required|email');
		$company_website = validate('Company_website','company_website','required');
		$description = validate('Description','description','required');
		// $target_country = validate('Target_country','target_country','required');
		$audience = validate('Audience','audience','required');
		$goals = validate('Goals','goals','required');
		$presence = validate('Presence','presence','required');
		$competitor = validate('Competitor','competitor','');
		$budget = validate('Budget','budget','required');
		$platforms = validate('Platforms','platforms','required');
		$preferences = validate('Preferences','preferences','required');
		$analytics = validate('Analytics','analytics','required');
		$timeline = validate('Timeline','timeline','required');
		$brand_guidlines = validate('Brand_guidlines','brand_guidlines','required');
		$prev_campaigns = validate('Prev_campaigns','prev_campaigns','');
		$kpi = validate('Kpi','kpi','required');
		$usp = validate('Usp','usp','required');
		$internal_resource = validate('Internal_resource','internal_resource','required');
		if(empty($error)){
			$error['form'] = 'smm-brief';		
			require 'mail.php';
			ob_start();
			include ($smmbrief_template);
			$smmbrief_template = ob_get_clean();
			$res = send_mail($brief_email, $smmbrief_subject, $smmbrief_template);
			if($res){
				header('Location:'. $domainname.'/thankyou');          		
          		die();
			}
			else{
				throw new Exception('Email sending failed.');
			}
		}	
	}
	elseif(!empty($_POST['logo-brief'])){
		$name = validate('Name','name', 'required|name');
		$email = validate('Email','email','required|email');
		$logo_name = validate('Logo Name','logo_name','required');
		$company_slogan = validate('Company_slogan','company_slogan','');
		$description = validate('Description','description','required');
		$bussiness_about = validate('Bussiness_about','bussiness_about','required');
		$additional_detail = validate('Additional_detail','additional_detail','required');
		$primarycolor = validate('Primarycolor','primarycolor','');
		$secondarycolor = validate('Secondarycolor','secondarycolor','');
		$person_name = validate('Person_name','person_name','');
		$person_email = validate('Person_email','person_email','');
		$designation = validate('Designation','designation','');
		$person_phone = validate('Person_phone','person_phone','');
		$company_address = validate('Company_address','company_address','');
		$company_phone = validate('Company_phone','company_phone','');

		if (($_FILES['LogoFile']['name']!="")){
			$target_Path = "upload/";
			$temp = explode(".", $_FILES['LogoFile']['name']);
			$newfilename = round(microtime(true)) . '.' . end($temp);			
			$target_Path = $target_Path.basename( $newfilename );
			move_uploaded_file($_FILES['LogoFile']['tmp_name'], $target_Path);
			$file = "<a href='".$base_url.$target_Path."'>Download</a>";
		}	

		if(empty($error)){
			$error['form'] = 'logo-brief';		
			require 'mail.php';
			ob_start();
			include ($logobrief_template);
			$logobrief_template = ob_get_clean();
			$res = send_mail($brief_email, $logobrief_subject, $logobrief_template);
			if($res){
				header('Location:https://thebookprofessionals.com/thankyou');
          		die();
			}
			else{
				throw new Exception('Email sending failed.');
			}
		}
	}
	elseif(!empty($_POST['invoice-link'])){
		$amount = validate('Amount','amount', 'required|number');
		$currency = validate('Currency','currency', 'required');
		$name = validate('Client\'s Name','name','required|name');
		$description = validate('Description','desc','required');
		$package = validate('Package','package','required');
		$agents_email = validate('Agent\'s Email','sales_email','required|email');
		$clients_email = validate('Client\'s Email','clients_email','required|email');
		
		$link_token = md5(uniqid(date('M-DD-YYYY-h-i-s-a'), true));
    
        $paymentlink = $base_url.'payment/paynow?token='.$link_token;
		
		if(empty($error)){
			$error['form'] = 'invoice-link';		
			require 'mail.php';
			ob_start();
			include ($invoicelink_template);
			$invoicelink_template = ob_get_clean();
			
			$newfile = 'invoice/'.$link_token.'.json';
            $data = array(              
                'amount' => $amount,
                'currency' => $currency,
                'name' => $name,
                'email' => $clients_email,
                'sales_email' => $agents_email,
                'desc' => $description,
                'packages' => $package,
                'payment_status' => false
            );
            ob_start();
            $data = json_encode($data);
            if( is_dir('invoice') === false ){
                mkdir('invoice');
            }

            file_put_contents($newfile, $data); 
            
			$res = send_mail($clients_email, $invoicelink_subject, $invoicelink_template, $agents_email);
			if($res){
				header('Location:'. $base_url.'payment/link_details?token='.$link_token);          		
          		die();
			}
			else{
				throw new Exception('Email sending failed.');
			}
		}	
	}
} 
