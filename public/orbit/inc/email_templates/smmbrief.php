<?php
echo 
'
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>'.$smmbrief_subject.'</title>
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
                    '.$smmbrief_subject.'
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
                                                Company Name: 
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['company_name'].'
			                    				</td>
			                    			</tr>
			                    			<tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Contact person name: 
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['contact_pname'].'
			                    				</td>
			                    			</tr>
			                    			<tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Email address:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['email'].'
			                    				</td>
			                    			</tr>
			                    			<tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Company website* and social media handles (if any):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['company_website'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Brief description of the company and its products/services:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['description'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Target audience (demographics, interests, etc.):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['audience'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Social media goals (e.g. increase brand awareness, drive website traffic, generate leads/sales, etc.):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['goals'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Current social media presence (if any):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['presence'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                    Competitors social media presence (if known):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['competitor'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Budget for paid social media marketing services:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['budget'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Desired social media platforms (e.g. Facebook, Instagram, Twitter, LinkedIn, etc.):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['platforms'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Content preferences (e.g. types of posts, frequency of posting, tone of voice, etc.):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['preferences'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Reporting and analytics requirements (e.g. monthly reports, ad performance metrics, etc.):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['analytics'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Timeline for social media marketing services:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['timeline'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Brand guidelines (if any):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['brand_guidlines'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
                                                <th style="background:#be3455;30; padding:10px 5px">
                                                Previous social media marketing campaigns (if any):
                                                </th>
                                                <td style="background:#ceff1a26; padding:10px 5px" colspan="2">
                                                '.$_POST['prev_campaigns'].'
                                                </td>
                                            </tr>	

                                            <tr>
                                                <th style="background:#be3455;30; padding:10px 5px">
                                                Key performance indicators (KPIs) for social media marketing (e.g. engagement rate, click-through rate, conversion rate, etc.):
                                                </th>
                                                <td style="background:#ceff1a26; padding:10px 5px" colspan="2">
                                                '.$_POST['kpi'].'
                                                </td>
                                            </tr>
                                            <tr>
                                                <th style="background:#be3455;30; padding:10px 5px">
                                                Unique selling proposition (USP) of the companys products/services:
                                                </th>
                                                <td style="background:#ceff1a26; padding:10px 5px" colspan="2">
                                                '.$_POST['usp'].'
                                                </td>
                                            </tr>
                                            <tr>
                                                <th style="background:#be3455;30; padding:10px 5px">
                                                Internal resources available for social media marketing (e.g. in-house content creation, etc.):
                                                </th>
                                                <td style="background:#ceff1a26; padding:10px 5px" colspan="2">
                                                '.$_POST['internal_resource'].'
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