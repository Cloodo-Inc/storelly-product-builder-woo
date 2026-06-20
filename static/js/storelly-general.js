"use strict";

jQuery(document).ready(function ($) {
  function notify(msg, tone) {
    if (window.spbwcDialog) { window.spbwcDialog.toast({ message: msg, tone: tone || "error" }); }
    else { window.alert(msg); }
  }
  $("#storelly_order_design_check_all").on("click", function () {
    if ($(this).is(":checked")) {
      $(".storelly_order_item_id").prop("checked", true);
    } else {
      $(".storelly_order_item_id").prop("checked", false);
    }
  });

  $("#storelly_download_design_by_type").on("click", function (e) {
    e.preventDefault();
    var item_ids = [];
    var order_id = $("#post_ID").val();
    $(".storelly_order_item_id:checkbox:checked").each(function (v, i) {
      item_ids.push($(this).val());
    });
    if (!order_id) {
      notify("Something went wrong", "error");
      return;
    }
    if (item_ids.length === 0) {
      notify("No design selected.", "warning");
      return;
    }
    var designType = $('[name="storelly_design_type_download"]').val();
    $("#storelly_order_submit_loading").removeClass("storelly_loaded");
    $("#storelly_download_design_by_type").attr("disabled", true);
    jQuery
      .ajax({
        url: storelly_admin.url,
        method: "POST",
        data: {
          action: "spbwc_download_order_designs",
          order_id: order_id,
          item_ids: item_ids,
          type_download: designType,
          nonce: storelly_admin.nonce,
        },
      })
      .done(function (data) {
        $("#storelly_order_submit_loading").addClass("storelly_loaded");
        $("#storelly_download_design_by_type").attr("disabled", false);
        var res;
        try {
          res = JSON.parse(data);
        } catch (e) {
          res = null;
        }
        if (res && res.flag == 1 && res.file) {
          var link = document.createElement("a");
          link.href = res.file;
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
        } else if (res && res.locked) {
          // Cloud feature locked (F2): show the upsell instead of a misleading
          // "no files" notice. The merchant upgrades from Account & Plan.
          notify(
            (res.mes || "This is a Cloud feature.") +
              " Upgrade from Account & Plan to unlock.",
            "warning"
          );
        } else {
          // No files came back. For PDF/SVG this usually means the print-ready
          // files have not been generated yet for this design.
          notify(
            "No files are available for the selected format yet. Try \"Regenerate PDFs\", or pick PNG / PNG preview.",
            "warning"
          );
        }
      })
      .fail(function () {
        $("#storelly_order_submit_loading").addClass("storelly_loaded");
        $("#storelly_download_design_by_type").attr("disabled", false);
        notify("Download failed. Please try again.", "error");
      });
  });
  
  if (jQuery('#the-list .no-items').length == 0) {
    jQuery('p.note-text').css('display', 'none');
  }
});
