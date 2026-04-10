<?php
echo 
'
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>'.$seobrief_subject.'</title>
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
                    '.$seobrief_subject.'
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
                                                Company Website:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['company_website'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Description:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['description'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Target Country:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['target_country'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Would you like to target locally? (if applicable):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['target'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Target audience:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['audience'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                SEO goals (e.g. increase website traffic, improve search engine rankings, generate leads/sales, etc.):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['goals'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Current SEO presence (if any):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['presence'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Competitors SEO presence (if known):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['competitor'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Budget for SEO services:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['budget'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Keywords or phrases that the company wants to rank for:
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['keywords'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Desired search engines (e.g. Google, Bing, etc.):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['search_engine'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
			                    				<th style="background:#be3455;30; padding:10px 5px">
                                                Content preferences (e.g. types of content, frequency of publishing, tone of voice, etc.):
			                    				</th>
			                    				<td style="background:#ceff1a26; padding:10px 5px" colspan="2">
			                    					'.$_POST['Content preferences'].'
			                    				</td>
			                    			</tr>	
                                            <tr>
                                                <th style="background:#be3455;30; padding:10px 5px">
                                                Reporting and analytics requirements (e.g. monthly reports, keyword rankings, traffic sources, etc.):
                                                </th>
                                                <td style="background:#ceff1a26; padding:10px 5px" colspan="2">
                                                    '.$_POST['analytics'].'
                                                </td>
                                            </tr>	    
                                            <tr>
                                                <th style="background:#be3455;30; padding:10px 5px">
                                                Do you have Google Analytics/Console/Tag Manager already set up?:
                                                </th>
                                                <td style="background:#ceff1a26; padding:10px 5px" colspan="2">
                                                    '.$_POST['google_analytics'].'
                                                </td>
                                            </tr>       
                                            <tr>
                                                <th style="background:#be3455;30; padding:10px 5px">
                                                Timeline for SEO services:
                                                </th>
                                                <td style="background:#ceff1a26; padding:10px 5px" colspan="2">
                                                    '.$_POST['timeline'].'
                                                </td>
                                            </tr>
                                            <tr>
                                                <th style="background:#be3455;30; padding:10px 5px">
                                                Additional comments or questions.:
                                                </th>
                                                <td style="background:#ceff1a26; padding:10px 5px" colspan="2">
                                                    '.$_POST['comments'].'
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