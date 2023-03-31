"use strict";

jQuery(document).ready(function ($) {
  $("#printcart_order_design_check_all").on("click", function () {
    if ($(this).is(":checked")) {
      $(".printcart_order_item_id").prop("checked", true);
    } else {
      $(".printcart_order_item_id").prop("checked", false);
    }
  });

  $("#printcart_download_design_by_type").on("click", function (e) {
    e.preventDefault();
    var item_ids = [];
    var order_id = $("#post_ID").val();
    $(".printcart_order_item_id:checkbox:checked").each(function (v, i) {
      item_ids.push($(this).val());
    });
    if (!order_id) {
      alert("Something went wrong");
      return;
    }
    if (item_ids.length === 0) {
      alert("No design selected.");
    }
    var designType = $('[name="printcart_design_type_download"]').val();
    $("#printcart_order_submit_loading").removeClass("printcart_loaded");
    $("#printcart_download_design_by_type").attr("disabled", true);
    jQuery
      .ajax({
        url: printcart_admin.url,
        method: "POST",
        data: {
          action: "printcart_download_order_designs",
          order_id: order_id,
          item_ids: item_ids,
          type_download: designType,
        },
      })
      .done(function (data) {
        $("#printcart_order_submit_loading").addClass("printcart_loaded");
        $("#printcart_download_design_by_type").attr("disabled", false);
        var res = JSON.parse(data);
      });
  });
});
