
(function () {
  "use strict";
  var jQueryPlugin = (window.jQueryPlugin = function (ident, func) {
    return function (arg) {
      if (this.length > 1) {
        this.each(function () {
          var $this = $(this);

          if (!$this.data(ident)) {
            $this.data(ident, func($this, arg));
          }
        });

        return this;
      } else if (this.length === 1) {
        if (!this.data(ident)) {
          this.data(ident, func(this, arg));
        }

        return this.data(ident);
      }
    };
  });
})();

(function () {
  "use strict";
  var TooltipStyle =
    "<style id='aks-tooltip-style'>[data-tooltip]{position:relative;display:inline-block}[data-tooltip] .aks-tooltip{position:absolute;width:fit-content;min-width:fit-content;padding:6px 10px;border-radius:5px;box-shadow:0 1em 2em -.5em rgba(0,0,0,.35);background:#020204;opacity:0;color:#fff;font-size:13px;font-weight:400;text-align:center;text-transform:none;line-height:1;user-select:none;pointer-events:none;visibility:hidden;z-index:1}[data-tooltip] .aks-tooltip::after{display:inline-block;position:absolute;content:''}[data-tooltip-location=up] .aks-tooltip{bottom:calc(100% + 10px);left:50%;transform:translateX(-50%);animation:tooltips-vert .3s ease-out forwards}[data-tooltip-location=up] .aks-tooltip::after{bottom:-4px;left:50%;transform:translateX(-50%);border-top:5px solid #020204;border-right:5px solid transparent;border-left:5px solid transparent}[data-tooltip-location=down] .aks-tooltip{top:calc(100% + 10px);left:50%;transform:translateX(-50%);animation:tooltips-vert .3s ease-out forwards}[data-tooltip-location=down] .aks-tooltip::after{top:-4px;left:50%;transform:translateX(-50%);border-right:5px solid transparent;border-bottom:5px solid #020204;border-left:5px solid transparent}[data-tooltip-location=left] .aks-tooltip{top:50%;right:calc(100% + 10px);transform:translateY(-50%);animation:tooltips-horz .3s ease-out forwards}[data-tooltip-location=left] .aks-tooltip::after{top:50%;right:-4px;transform:translateY(-50%);border-top:5px solid transparent;border-bottom:5px solid transparent;border-left:5px solid #020204}[data-tooltip-location=right] .aks-tooltip{top:50%;left:calc(100% + 10px);transform:translateY(-50%);animation:tooltips-horz .3s ease-out forwards}[data-tooltip-location=right] .aks-tooltip::after{top:50%;left:-4px;transform:translateY(-50%);border-top:5px solid transparent;border-right:5px solid #020204;border-bottom:5px solid transparent}@-moz-keyframes tooltips-vert{to{opacity:.9;transform:translate(-50%,0)}}@-webkit-keyframes tooltips-vert{to{opacity:.9;transform:translate(-50%,0)}}@-o-keyframes tooltips-vert{to{opacity:.9;transform:translate(-50%,0)}}@keyframes tooltips-vert{to{opacity:.9;transform:translate(-50%,0)}}@-moz-keyframes tooltips-horz{to{opacity:.9;transform:translate(0,-50%)}}@-webkit-keyframes tooltips-horz{to{opacity:.9;transform:translate(0,-50%)}}@-o-keyframes tooltips-horz{to{opacity:.9;transform:translate(0,-50%)}}@keyframes tooltips-horz{to{opacity:.9;transform:translate(0,-50%)}}</style>";
  $("head").append(TooltipStyle);

  function Tooltip($root) {
    const element = $root;
    const tooltip_el = $root.first("[data-tooltip]");
    const tooltip = $root.data("tooltip");
    element.append('<span class="aks-tooltip">' + tooltip + "</span>");
    const tooltip_container = $root.find(".aks-tooltip");

    tooltip_el.mousemove(function (event) {
      tooltip_container.css({ opacity: "1", visibility: "visible" });
    });
    tooltip_el.mouseout(function (event) {
      tooltip_container.css({ opacity: "0", visibility: "hidden" });
    });
  }

  $.fn.Tooltip = jQueryPlugin("Tooltip", Tooltip);
  $("[data-tooltip]").Tooltip();
})();
