!function (e) { "use strict"; e(document).ready(function () { e(window).width(), e(".owl-carousel").owlCarousel({ loop: !0, autoplay: true,margin: 10, nav: !1, items: 1 }), e(".mobile-header .bar input[type='checkbox']").click(function () { e(this).is(":checked") ? e(".mobile-header-items").slideDown(500) : e(".mobile-header-items").slideUp(500) }) }) }(jQuery);


