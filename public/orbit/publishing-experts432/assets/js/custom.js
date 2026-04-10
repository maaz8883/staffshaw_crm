// blogslider start
$('.testi-slider').slick({
  dots: false,
  arrows: true,
  infinite: false,
  speed: 300,
  slidesToShow: 1,
  slidesToScroll: 1,
  responsive: [
    {
      breakpoint: 1024,
      settings: {
        slidesToShow: 1,
        slidesToScroll: 1,
        infinite: true,
        dots: false
      }
    },
    {
      breakpoint: 600,
      settings: {
        slidesToShow: 1,
        slidesToScroll: 1
      }
    },
    {
      breakpoint: 480,
      settings: {
        slidesToShow: 1,
        slidesToScroll: 1
      }
    }
  ]
});




// book slider start
$('.book-slider').slick({
  dots: false,
  arrows: false,
  infinite: true,
  speed: 300,
  autoplay:true,
  autoplaySpeed:2000,
  slidesToShow: 4,
  slidesToScroll: 1,
  responsive: [
    {
      breakpoint: 1024,
      settings: {
        slidesToShow: 3,
        slidesToScroll: 1,
      }
    },
    {
      breakpoint: 600,
      settings: {
        slidesToShow: 2,
        slidesToScroll: 1,
        arrows: true,
      }
    },
    {
      breakpoint: 480,
      settings: {
        slidesToShow: 1,
        slidesToScroll: 1,
        arrows: true,
      }
    }
  ]
});



// blogslider end
// brand slider start
$('.brand-slider').slick({
  slidesToShow: 8,
  slidesToScroll: 1,
  autoplay: true,
  autoplaySpeed: 0,
  speed: 3000,
  arrows: false,
  dots: false,
  pauseOnHover: false,
  cssEase: 'linear',
  responsive: [{
    breakpoint: 1100,
    settings: {
      slidesToShow: 5,
      slidesToScroll: 1,
      infinite: true,
      dots: false
    }
  },
  {
    breakpoint: 900,
    settings: {
      slidesToShow: 3,
      slidesToScroll: 1
    }
  },
  {
    breakpoint: 500,
    settings: {
      slidesToShow: 2,
      slidesToScroll: 1,
      dots: false
    }
  }
  ]
});

// brand slider end
// brand slider start
$('.brand-slider-2').slick({
  slidesToShow: 8,
  slidesToScroll: 1,
  autoplay: true,
  autoplaySpeed: 0,
  speed: 4000,
  arrows: false,
  dots: false,
  pauseOnHover: false,
  cssEase: 'linear',
  responsive: [{
    breakpoint: 1100,
    settings: {
      slidesToShow: 5,
      slidesToScroll: 1,
      infinite: true,
      dots: false
    }
  },
  {
    breakpoint: 900,
    settings: {
      slidesToShow: 3,
      slidesToScroll: 1
    }
  },
  {
    breakpoint: 500,
    settings: {
      slidesToShow: 2,
      slidesToScroll: 1,
      dots: false
    }
  }
  ]
});

// brand slider end
// product slider jas start

$('.slider-for').slick({
  slidesToShow: 1,
  slidesToScroll: 1,
  arrows: false,
  fade: true,
  asNavFor: '.slider-nav'
});
$('.slider-nav').slick({
  slidesToShow: 3,
  slidesToScroll: 1,
  asNavFor: '.slider-for',
  dots: true,
  centerMode: true,
  focusOnSelect: true
});
// product slider jas end

// simple slick slider start
$(".regular").slick({
  dots: true,
  infinite: true,
  speed: 300,
  autoplay: true,
  slidesToShow: 3,
  slidesToScroll: 3
});

// simple slick slider end

// wow animation js 

$(function () {
  new WOW().init();
});


// responsive menu js 

$(function () {
  $('#menu').slicknav();
});



// slick slider in tabs js start

function openCity(evt, cityName) {
  // Declare all variables
  var i, tabcontent, tablinks;

  // Get all elements with class="tabcontent" and hide them
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }

  // Get all elements with class="tablinks" and remove the class "active"
  tablinks = document.getElementsByClassName("tablinks");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace("active", "");
  }

  // Show the current tab, and add an "active" class to the button that opened the tab
  document.getElementById(cityName).style.display = "block";
  evt.currentTarget.className += "active";
}


// slick slider in tabs js end



// search button js

(function ($) {

  $.fn.searchBox = function (ev) {

    var $searchEl = $('.search-elem');
    var $placeHolder = $('.placeholder');
    var $sField = $('#search-field');

    if (ev === "open") {
      $searchEl.addClass('search-open')
    };

    if (ev === 'close') {
      $searchEl.removeClass('search-open'),
        $placeHolder.removeClass('move-up'),
        $sField.val('');
    };

    var moveText = function () {
      $placeHolder.addClass('move-up');
    }

    $sField.focus(moveText);
    $placeHolder.on('click', moveText);

    $('.submit').prop('disabled', true);
    $('#search-field').keyup(function () {
      if ($(this).val() != '') {
        $('.submit').prop('disabled', false);
      }
    });
  }

}(jQuery));

$('.search-btn').on('click', function (e) {
  $(this).searchBox('open');
  e.preventDefault();
});

$('.close').on('click', function () {
  $(this).searchBox('close');
});



// brand slider end
// blogslider start
$('.we-do-slider').slick({
    dots: false,
    arrows: true,
    infinite: true,
    speed: 300,
    // focusOnSelect: true,
    centerMode: false,
    centerPadding: '0px',
    slidesToShow: 4,
    slidesToScroll: 1,
    responsive: [{
            breakpoint: 1100,
            settings: {
                slidesToShow: 3,
                slidesToScroll: 1,
                infinite: true,
                dots: false
            }
        },
        {
            breakpoint: 900,
            settings: {
                slidesToShow: 2,
                slidesToScroll: 1
            }
        },
        {
            breakpoint: 500,
            settings: {
                slidesToShow: 1,
                slidesToScroll: 1,
                dots: false
            }
        }
    ]
});

if($(window).width() > 768){
    $('.we-slider-box').click(function(){
        let ind = $(this).closest('.slick-slide').attr('data-slick-index');
        $('.we-do-slider').slick('slickGoTo', ind - 1);
    });
}

if($(window).width() < 768){

  $('.package-slidr').slick({
    dots: false,
    arrows: true,
    infinite: true,
    speed: 300,
    autoplay:true,
    autoplaySpeed:2000,
    slidesToShow: 1,
    slidesToScroll: 1,
  });
}


$('.testimonial-image-icon>a').click(function(e){
    e.preventDefault();
    $('.video-ovrlap > video').get(0).play();
    $('.video-ovrlap > video').attr('controls', true)
    $(this).hide();
})

$('.we-do-slider').on('afterChange', function() {
    $('.slick-slide').removeClass('bottomm');
    $(this).find('.slick-slide.slick-current+.slick-active').next().addClass('bottomm')
});
