
<style type="text/css">
	:root {
        --clr-1:81, 223, 207;
    --clr-2:00, 138, 106;
    --clr-3:0, 128, 128;
    --clr-4:0, 43, 91;
    --clr-5:188, 108, 37;
    --dark-color:0, 0, 0;
    --light-color:255, 255, 255;}
</style>
<!-- Start of LiveChat (www.livechat.com) code -->
<!--<link rel="preconnect" href="https://www.livechat.com/"> <link rel="dns-prefetch" href="https://www.livechat.com/">-->
<!--<script>-->

<!--   window.__lc=window.__lc||{},window.__lc.license=18794649,window.__lc.integration_name="manual_onboarding",window.__lc.product_name="livechat",function(n,t,c){function e(n){return l._h?l._h.apply(null,n):l._q.push(n)}var l={_q:[],_h:null,_v:"2.0",on:function(){e(["on",c.call(arguments)])},once:function(){e(["once",c.call(arguments)])},off:function(){e(["off",c.call(arguments)])},get:function(){if(!l._h)throw Error("[LiveChatWidget] You can't use getters before load.");return e(["get",c.call(arguments)])},call:function(){e(["call",c.call(arguments)])},init:function(){var n=t.createElement("script");n.async=!0,n.type="text/javascript",n.src="https://cdn.livechatinc.com/tracking.js",t.head.appendChild(n)}};n.__lc.asyncInit||l.init(),n.LiveChatWidget=n.LiveChatWidget||l}(window,document,[].slice);-->
<!--</script>-->
<!--<noscript><a href="https://www.livechat.com/chat-with/18794649/" rel="nofollow">Chat with us</a>, powered by <a href="https://www.livechat.com/?welcome" rel="noopener nofollow" target="_blank">LiveChat</a></noscript>-->

<!--<script>-->
<!--    let eventCounter=0;function onNewEvent(e){1!=++eventCounter&&"message"===e.type&&LiveChatWidget.call("maximize")}LiveChatWidget.on("new_event",onNewEvent);-->
     
<!--   $(document).ready(function(){$(".chat, .chatt").click(function(){LiveChatWidget.call("maximize")})});-->

<!--    </script>-->
<!--<style>-->
<!--    #chat-widget-container{-->
<!--        max-height:500px !Important;-->
<!--    }-->
<!--</style>-->
<!-- End of LiveChat code -->
<link rel="preconnect" href="https://cdnjs.cloudflare.com"> <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.11/jquery.mask.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/intlTelInput-jquery.min.js" integrity="sha512-sVhsc+r7sEickzS6LohO+VDVv2ler/3QY7op8ScWV8KVLLq+m1WAl6uplr/YHmqI0L0j99ehNRh2cIwn7zXcdg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.12/js/utils.min.js"></script>

<script> function validate(){var e=$("input[name='phone']").intlTelInput("getNumber");iso=$("input[name='phone']").intlTelInput("getSelectedCountryData").iso2;var t=intlTelInputUtils.getExampleNumber(iso,0,0);""==e&&(e=t);var o=intlTelInputUtils.formatNumber(e,iso,intlTelInputUtils.numberFormat.NATIONAL),n=intlTelInputUtils.isValidNumber(e,iso),a=intlTelInputUtils.getValidationError(e,iso);console.log(e),console.log(o),console.log(intlTelInputUtils.formatNumber(e,iso,intlTelInputUtils.numberFormat.INTERNATIONAL)),console.log(intlTelInputUtils.formatNumber(e,iso,intlTelInputUtils.numberFormat.E164)),console.log(intlTelInputUtils.formatNumber(e,iso,intlTelInputUtils.numberFormat.RFC3966)),console.log(n),console.log(a)}$("input[name='phone']").intlTelInput({geoIpLookup:function(e){$.get("https://ipinfo.io",function(){},"jsonp").always(function(t){e(t&&t.country?t.country:"")})},initialCountry:"auto",separateDialCode:!0}),$("input[name='phone']").on("countrychange",function(e){$(this).val("");var t=$(this).intlTelInput("getSelectedCountryData"),o=t.dialCode,n=intlTelInputUtils.getExampleNumber(t.iso2,0,0);console.log("placeholder > "+n),n=intlTelInputUtils.formatNumber(n,t.iso2,2),console.log("placeholder > "+n),n=n.replace("+"+o+" ",""),console.log("placeholder > "+n),mask=n.replace(/[0-9+]/gi,"0"),$("input[name='phone']").mask(mask,{placeholder:n})}),$("input[name='phone']").on("input",function(){/^1/.test(this.value)&&(this.value=this.value.replace(/^1/,""))});</script>
<?php
$output = ob_get_flush();
if (!empty($cachefile)) {
	file_put_contents($cachefile, $output);
}
?>
</body>



</html>