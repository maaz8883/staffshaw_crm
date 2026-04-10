 <?php session_start();?> 
<style>
 /*@import url('https://fonts.googleapis.com/css?family=Raleway:400,600,900');*/

*{
  box-sizing:border-box;
 /* outline:1px solid ;*/
}

body{
        background:rgba(var(--clr-2),1);
        height: 100%;
        margin: 0;
        background-repeat: no-repeat;
        background-attachment: fixed;
  
}
a {
  color: #fff;s
  text-decoration: none;
  transition: all 0.30s linear 0s;
}

.wrapper-1{
  width:100%;
  height:100vh;
  display: flex;
flex-direction: column;
}
.wrapper-2{
  padding: 30px;
  text-align:center;
}
h1{
  font-family: 'Raleway', Arial Black, Sans-Serif;
  font-size:4em;
  font-weight: 900;
  letter-spacing:3px;
  color: #fafafa;
  margin:0;
  margin-top: 30px;
  margin-bottom:20px;
}
.wrapper-2 p{
  margin:0;
  font-size:1.3em;
  color:#fafafa;
  font-family: 'Raleway', sans-serif;
  letter-spacing:1px;
  line-height: 1.5;
}
.footer-like{
  margin-top: auto; 
  background: rgba(var(--clr-1),1);
  padding:6px;
  text-align:center;
}
.footer-like p {
  /*rgb(94, 81, 81)*/
  margin:0;
  padding:4px;
  color:#fafafa;
  font-family: 'Raleway', sans-serif;
  letter-spacing:1px;
}
.footer-like p a{
  text-decoration:none;
  color:#5892FF;
  font-weight: 600;
}

.footer-like p a:hover{
  color:#FFF;
 }

@media (min-width:360px){
  h1{
    font-size:4.5em;
  }

}

@media (min-width:600px){
  .thankyoucontent{
  max-width:1000px;
  margin:0 auto;
}
  .wrapper-1{
  height: initial;
  max-width:620px;
  margin:0 auto;
  margin-top:50px;
  }
  
  
}
.wrapper-2 img{
  max-width: 200px;
}
</style>

<section class="bg-2">
<div class="container-fluid vh-100">
  <div class="row h-100 align-items-center">
    <div class="col-12">
      <div class="thankyoucontent">
 <div class="wrapper-1">
    <div class="wrapper-2">
       <img src="https://i.ibb.co/Lkn7rkG/thank-you-envelope.png" alt="thank-you-envelope" style="border:0px">
     <h1>Thank you!</h1>
      <p>for contacting us, we will reply promptly</p> 
      <p>once your message is received. </p>
      <button onclick="history.back()" class="back mt-3">
        PREVIOUS PAGE</button>
      
    </div>
   
    <div class="footer-like">
      <p>
      </p>
    </div>
</div>
</div>
    </div>
  </div>
</div>
</section>

