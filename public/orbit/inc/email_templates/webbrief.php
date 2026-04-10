<?php
echo 
'
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>'.$webbrief_subject.'</title>
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
                    '.$webbrief_subject.'
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
                                                    Exact name of your business/organization:
                                                </th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['business_name'].'
			                    				</td>
			                    			</tr>
			                    			<tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Kindly state the purpose of your website: (Is it a selling/informative website or a personal blog? etc.):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['purpose'].'
			                    				</td>
			                    			</tr>
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
												    As per the navigation of your website, kindly state the title of your WebPages: (E.g.: Company Profile, Contact us etc.):
                                                </th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['navigation'].'
			                    				</td>
			                    			</tr>	
			                    			<tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
												    What type of overall feeling would you like to project with your new Website Domain? (Corporate, fun, high-tech, etc...) Leave this field blank if you would like us to make this determination:
                                                </th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['feeling'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Please give a brief description of your business: (What type of products or services does your company supply? etc...):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['description'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    State the target audience of your website:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['audience'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Do you have any specific design, preferences? If yes, please mention a reference: (If you have any particular likes or dislikes regarding any competitor\'s website etc.):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['design_specification'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Do you have any additional comments:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['comments'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Do you have a Domain Name:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['domain'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Do you want us to provide hosting for your website:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['hosting'].'
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