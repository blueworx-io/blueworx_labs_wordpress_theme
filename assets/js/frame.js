/**
 * The phone menu.
 *
 * The drawer is a <details> element, so it opens and closes with no script.
 * This only adds what a details element cannot do on its own: close when the
 * visitor taps elsewhere or presses Escape, close again when the window grows
 * past the phone width, and tell assistive technology whether it is open.
 */
(function () {
  var PHONE_WIDTH = 900; // Mirrored in frame.css and includes/frame.php.

  function setUp(menu) {
    var button = menu.querySelector('summary');

    if (!button) {
      return;
    }

    function reflect() {
      button.setAttribute('aria-expanded', menu.open ? 'true' : 'false');
    }

    menu.addEventListener('toggle', reflect);
    reflect();

    document.addEventListener('click', function (event) {
      if (menu.open && !menu.contains(event.target)) {
        menu.open = false;
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && menu.open) {
        menu.open = false;
        button.focus();
      }
    });

    window.addEventListener('resize', function () {
      if (menu.open && window.innerWidth >= PHONE_WIDTH) {
        menu.open = false;
      }
    });
  }

  var menus = document.querySelectorAll('.blueworx-menu');

  for (var i = 0; i < menus.length; i += 1) {
    setUp(menus[i]);
  }
})();
