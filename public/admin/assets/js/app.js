$(function() {
    "use strict";

    // Vérifie si l'élément avec la classe "header-message-list" existe
    if ($(".header-message-list").length) {
        // Initialise PerfectScrollbar pour les listes de messages et de notifications
        if ($(".header-message-list").length) {
            new PerfectScrollbar(".header-message-list");
        }
        if ($(".header-notifications-list").length) {
            new PerfectScrollbar(".header-notifications-list");
        }
    }

    // Gestion des événements de clic pour divers boutons et éléments
    $(".mobile-search-icon").on("click", function() {
        if ($(".search-bar").length) {
            $(".search-bar").addClass("full-search-bar");
        }
    });

    $(".search-close").on("click", function() {
        if ($(".search-bar").length) {
            $(".search-bar").removeClass("full-search-bar");
        }
    });

    $(".mobile-toggle-menu").on("click", function() {
        if ($(".wrapper").length) {
            $(".wrapper").addClass("toggled");
        }
    });

    $(".toggle-icon").click(function() {
        if ($(".wrapper").length) {
            if ($(".wrapper").hasClass("toggled")) {
                $(".wrapper").removeClass("toggled");
                $(".sidebar-wrapper").unbind("hover");
            } else {
                $(".wrapper").addClass("toggled");
                $(".sidebar-wrapper").hover(
                    function() {
                        $(".wrapper").addClass("sidebar-hovered");
                    },
                    function() {
                        $(".wrapper").removeClass("sidebar-hovered");
                    }
                );
            }
        }
    });

    // Gestion du bouton "Retour en haut"
    $(window).on("scroll", function() {
        if ($(".back-to-top").length) {
            if ($(this).scrollTop() > 300) {
                $(".back-to-top").fadeIn();
            } else {
                $(".back-to-top").fadeOut();
            }
        }
    });

    $(".back-to-top").on("click", function() {
        $("html, body").animate({
            scrollTop: 0
        }, 600);
        return false;
    });

    // Gestion du menu MetisMenu
    $(function() {
        var current = window.location;
        var $target = $(".metismenu li a").filter(function() {
            return this.href == current;
        }).addClass("").parent().addClass("mm-active");

        while ($target.is("li")) {
            $target = $target.parent("").addClass("mm-show").parent("").addClass("mm-active");
        }
    });

    $(function() {
        if ($("#menu").length && $.fn.metisMenu) {
            $("#menu").metisMenu();
        }
    });

    // Gestion des boutons de chat et de courriel
    $(".chat-toggle-btn").on("click", function() {
        if ($(".chat-wrapper").length) {
            $(".chat-wrapper").toggleClass("chat-toggled");
        }
    });

    $(".chat-toggle-btn-mobile").on("click", function() {
        if ($(".chat-wrapper").length) {
            $(".chat-wrapper").removeClass("chat-toggled");
        }
    });

    $(".email-toggle-btn").on("click", function() {
        if ($(".email-wrapper").length) {
            $(".email-wrapper").toggleClass("email-toggled");
        }
    });

    $(".email-toggle-btn-mobile").on("click", function() {
        if ($(".email-wrapper").length) {
            $(".email-wrapper").removeClass("email-toggled");
        }
    });

    $(".compose-mail-btn").on("click", function() {
        if ($(".compose-mail-popup").length) {
            $(".compose-mail-popup").show();
        }
    });

    $(".compose-mail-close").on("click", function() {
        if ($(".compose-mail-popup").length) {
            $(".compose-mail-popup").hide();
        }
    });

    // Gestion des thèmes et des couleurs
    $(".switcher-btn").on("click", function() {
        if ($(".switcher-wrapper").length) {
            $(".switcher-wrapper").toggleClass("switcher-toggled");
        }
    });

    $(".close-switcher").on("click", function() {
        if ($(".switcher-wrapper").length) {
            $(".switcher-wrapper").removeClass("switcher-toggled");
        }
    });

    $("#lightmode").on("click", function() {
        $("html").attr("class", "light-theme");
    });

    $("#darkmode").on("click", function() {
        $("html").attr("class", "dark-theme");
    });

    $("#semidark").on("click", function() {
        $("html").attr("class", "semi-dark");
    });

    $("#minimaltheme").on("click", function() {
        $("html").attr("class", "minimal-theme");
    });

    // Gestion des couleurs de l'en-tête
    $("#headercolor1").on("click", function() {
        $("html").addClass("color-header headercolor1").removeClass("headercolor2 headercolor3 headercolor4 headercolor5 headercolor6 headercolor7 headercolor8");
    });

    $("#headercolor2").on("click", function() {
        $("html").addClass("color-header headercolor2").removeClass("headercolor1 headercolor3 headercolor4 headercolor5 headercolor6 headercolor7 headercolor8");
    });

    $("#headercolor3").on("click", function() {
        $("html").addClass("color-header headercolor3").removeClass("headercolor1 headercolor2 headercolor4 headercolor5 headercolor6 headercolor7 headercolor8");
    });

    $("#headercolor4").on("click", function() {
        $("html").addClass("color-header headercolor4").removeClass("headercolor1 headercolor2 headercolor3 headercolor5 headercolor6 headercolor7 headercolor8");
    });

    $("#headercolor5").on("click", function() {
        $("html").addClass("color-header headercolor5").removeClass("headercolor1 headercolor2 headercolor4 headercolor3 headercolor6 headercolor7 headercolor8");
    });

    $("#headercolor6").on("click", function() {
        $("html").addClass("color-header headercolor6").removeClass("headercolor1 headercolor2 headercolor4 headercolor5 headercolor3 headercolor7 headercolor8");
    });

    $("#headercolor7").on("click", function() {
        $("html").addClass("color-header headercolor7").removeClass("headercolor1 headercolor2 headercolor4 headercolor5 headercolor6 headercolor3 headercolor8");
    });

    $("#headercolor8").on("click", function() {
        $("html").addClass("color-header headercolor8").removeClass("headercolor1 headercolor2 headercolor4 headercolor5 headercolor6 headercolor7 headercolor3");
    });

    // sidebar colors
    $('#sidebarcolor1').click(theme1);
    $('#sidebarcolor2').click(theme2);
    $('#sidebarcolor3').click(theme3);
    $('#sidebarcolor4').click(theme4);
    $('#sidebarcolor5').click(theme5);
    $('#sidebarcolor6').click(theme6);
    $('#sidebarcolor7').click(theme7);
    $('#sidebarcolor8').click(theme8);

    function theme1() {
        $('html').attr('class', 'color-sidebar sidebarcolor1');
    }

    function theme2() {
        $('html').attr('class', 'color-sidebar sidebarcolor2');
    }

    function theme3() {
        $('html').attr('class', 'color-sidebar sidebarcolor3');
    }

    function theme4() {
        $('html').attr('class', 'color-sidebar sidebarcolor4');
    }

    function theme5() {
        $('html').attr('class', 'color-sidebar sidebarcolor5');
    }

    function theme6() {
        $('html').attr('class', 'color-sidebar sidebarcolor6');
    }

    function theme7() {
        $('html').attr('class', 'color-sidebar sidebarcolor7');
    }

    function theme8() {
        $('html').attr('class', 'color-sidebar sidebarcolor8');
    }
});
