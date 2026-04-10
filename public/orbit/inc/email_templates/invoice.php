<?php
echo
'<html>
<style>
table, th, td {
    border: 1px solid black;
    border-collapse: collapse;
}
th {
    font-size: 14px;
    padding:5px 10px 5px 2px;
}
td{
    padding:5px 10px 5px 2px;
}
</style>
<body>
<div>
    <img src="'.$logo.'" class="mb-4" style="width: 200px;">
</div>
<h2>Invoice Details:</h2>
<table style="text-align:left; border: 1px solid black; border-collapse: collapse;">
    <tbody>
        <tr>
            <th style="font-size: 14px; padding:5px 10px 5px 2px; border: 1px solid black; border-collapse: collapse;">Client Name:</th>
            <td style="padding:5px 10px 5px 2px; border: 1px solid black; border-collapse: collapse;">' . $name . '</td>
        </tr>
        <tr>
            <th style="font-size: 14px; padding:5px 10px 5px 2px; border: 1px solid black; border-collapse: collapse;">Project Description:</th>
            <td style="padding:5px 10px 5px 2px; border: 1px solid black; border-collapse: collapse;">' . $desc . '</td>
        </tr>
        <tr>
            <th style="font-size: 14px; padding:5px 10px 5px 2px; border: 1px solid black; border-collapse: collapse;">Amount:</th>
            <td style="padding:5px 10px 5px 2px; border: 1px solid black; border-collapse: collapse;">' . $amount . $currency . '</td>
        </tr>       
        <tr>
            <th style="font-size: 14px; padding:5px 10px 5px 2px; border: 1px solid black; border-collapse: collapse;">Invoice Link :</th>
            <td style="padding:5px 10px 5px 2px; border: 1px solid black; border-collapse: collapse;"><a href="'.$base_url.'paynow.php?token=' . $link_token . '">'.$base_url.'paynow.php?token=' . $link_token . '</a></td>
        </tr>
    </tbody>
</table>
<br>
    <p>Please feel free to send us an email at '.$payment_email.' or call us at '.$no.' for billing related queries.</p>
    <p>Thank you for choosing <a href="'.$base_url.'"> '.$bname.'</a>.</p>
</body>
</html>
';
