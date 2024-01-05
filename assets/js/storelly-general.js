"use strict";

jQuery(document).ready(function ($) {
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
      alert("Something went wrong");
      return;
    }
    if (item_ids.length === 0) {
      alert("No design selected.");
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
          action: "storelly_download_order_designs",
          order_id: order_id,
          item_ids: item_ids,
          type_download: designType,
        },
      })
      .done(function (data) {
        $("#storelly_order_submit_loading").addClass("storelly_loaded");
        $("#storelly_download_design_by_type").attr("disabled", false);
        var res = JSON.parse(data);
        if (res.flag == 1 && res.file) {
          var link = document.createElement("a");
          link.href = res.file;
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
        }
      });
  });
});
