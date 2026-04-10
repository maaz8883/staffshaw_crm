<?php
echo 
'
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>'.$logobrief_subject.'</title>
</head>
<style type="text/css">
        body {
            width: 100%;
            background-color: #E7F2E4;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
            mso-margin-top-alt: 0px;
            mso-margin-bottom-alt: 0px;
            mso-padding-alt: 0px 0px 0px 0px;
        }
table.container590 {
    background: #f5f5f5;
    box-shadow: 0 0 36px #2323231a;
    
}
        p,
        h1,
        h2,
        h3,
        h4 {
            margin-top: 0;
            margin-bottom: 0;
            padding-top: 0;
            padding-bottom: 0;
        }

        span.preheader {
            display: none;
            font-size: 1px;
        }

        html {
            width: 100%;
        }

        table {
            font-size: 14px;
            border: 0;
        }
        .pad{
        	padding: 10px 5px;
        }
        .bg-1{
        	background:#be3455;
        }
        .bg-2{
        	background:#ceff1a26;
        }
        .bg-3{
        	background:#be3455;30;
        }
        .bg-1,.bg-2,.bg-3{
        	color: #fff;
        }
        @media only screen and (max-width: 640px) {
            
            .main-header {
                font-size: 20px !important;
            }
            .main-section-header {
                font-size: 28px !important;
            }
            .show {
                display: block !important;
            }
            .hide {
                display: none !important;
            }
            .align-center {
                text-align: center !important;
            }
            .no-bg {
                background: none !important;
            }
            .main-image img {
                width: 440px !important;
                height: auto !important;
            }
            .divider img {
                width: 440px !important;
            }
            .container590 {
                width: 440px !important;
            }
            .container580 {
                width: 400px !important;
            }
            .main-button {
                width: 220px !important;
            }
            .section-img img {
                width: 320px !important;
                height: auto !important;
            }
            .team-img img {
                width: 100% !important;
                height: auto !important;
            }
        }
        @media only screen and (max-width: 479px) {
            .main-header {
                font-size: 18px !important;
            }
            .main-section-header {
                font-size: 26px !important;
            
            .divider img {
                width: 280px !important;
            }
            .container590 {
                width: 280px !important;
            }
		}
            .container590 {
                width: 280px !important;
            }
            .container580 {
                width: 260px !important;
            .section-img img {
                width: 280px !important;
                height: auto !important;
            }
        }
    </style>
</head>
<body class="respond" leftmargin="0" topmargin="0" marginwidth="0" marginheight="0" >
    <table style="display:none!important;">
        <tr>
            <td>
                <div style="overflow:hidden;display:none;font-size:1px;color:#ffffff;line-height:1px;font-family:Arial;maxheight:0px;max-width:0px;opacity:0;">
                    '.$logobrief_subject.'
                </div>
            </td>
        </tr>
    </table>
    <table border="0" width="100%" cellpadding="0" cellspacing="0" >
        <tr>
            <td align="center">
                <table border="0" align="center" width="590" cellpadding="0" cellspacing="0" class="container590">                 
                    <tr>
                        <td align="center">
                            <table border="0" align="center" width="590" cellpadding="0" cellspacing="0" class="container590">
                                <tr>
                                    <td align="center" class=" pad" height="70" style="height:70px;">
                                        <a href="'.$base_url.'" style="display: block; border-style: none !important; border: 0 !important;"><img width="100" border="0" style="display: block; width: 100px;" src="'.$logo.'" alt="" /></a>
                                    </td>
                                </tr>
                                <tr>
			                    	<td>
			                    		<table class=" message" width="100%" style="text-align: left;">
			                    			<tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
			                    					Name: 
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['name'].'
			                    				</td>
			                    			</tr>
			                    			<tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
			                    					Email: 
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['email'].'
			                    				</td>
			                    			</tr>
			                    			<tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Logo Name which you want to use in Logo:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['logo_name'].'
			                    				</td>
			                    			</tr>
			                    			<tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Company Slogan which you want to use in Logo:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['company_slogan'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Competitors Reference Optional, you can enter multiple Links:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['description'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Describe your business what is your Business all about:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['bussiness_about'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    What you want in your logo Any additional details:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['additional_detail'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Primary Color Write the name or code:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['primarycolor'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Secondary Color Write the name or code:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['secondarycolor'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Person name:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['person_name'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Person\'s Email:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['person_email'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Person Designation:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['designation'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Person Phone Number:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['person_phone'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Company Address:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['company_address'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Company Phone:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['company_phone'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
                                                <th style="background:#be3455;30; padding:10px 5px">
                                                    File Link:
                                                </th>
                                                <td style="background:#ceff1a26; padding:10px 5px" colspan="2">
                                                    '.$file.'
                                                </td>
                                            </tr>	                                           			                    			
			                    		</table>
			                    	</td>
			                    </tr>
			                    <tr class="" style="background:#be3455;padding:10px 5px color:#fff"style="">
			                    	<td style="text-align: center;" class="bg-1 pad" >
			                    		 © '.date('Y').' '.$bname.'
			                    	</td>
			                    </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';