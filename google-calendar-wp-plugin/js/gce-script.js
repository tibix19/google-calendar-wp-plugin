jQuery(document).ready(function ($) {
  function updateEvents(filter) {
    var sortOrder = $("#gce-show-past-events").is(":visible")
      ? "future"
      : "past";
    $.ajax({
      url: gce_ajax.ajax_url,
      type: "POST",
      data: {
        action: "gce_filter_events",
        filter: filter,
        sort_order: sortOrder,
      },
      success: function (response) {
        $("#gce-events-list").html(response);
      },
    });
  }

  $(".gce-filter-btn").on("click", function () {
    $(".gce-filter-btn").removeClass("active");
    $(this).addClass("active");
    var filter = $(this).data("filter") || "all";
    updateEvents(filter);
  });

  $("#gce-show-past-events").on("click", function () {
    $(this).hide();
    $("#gce-show-future-events").show();
    updateEvents($(".gce-filter-btn.active").data("filter") || "all");
  });

  $("#gce-show-future-events").on("click", function () {
    $(this).hide();
    $("#gce-show-past-events").show();
    updateEvents($(".gce-filter-btn.active").data("filter") || "all");
  });
});
