jQuery(document).ready(function ($) {
  // Fonction principale pour mettre à jour la liste des événements en fonction des filtres et de l'ordre (passé ou futur)
  function updateEvents() {
    // Récupère l'état des filtres pour Athlétisme et Agrès (coche ou décoché)
    var filterAthletisme = $("#gce-filter-athletisme").is(":checked");
    var filterAgres = $("#gce-filter-agres").is(":checked");

    // Détermine si on doit afficher les événements futurs ou passés
    var sortOrder = $("#gce-show-past-events").is(":visible")
      ? "future"
      : "past";

    // Effectue une requête AJAX pour récupérer les événements filtrés et triés
    $.ajax({
      url: gce_ajax.ajax_url,
      type: "POST",
      data: {
        action: "gce_filter_events",
        filter_athletisme: filterAthletisme,
        filter_agres: filterAgres,
        sort_order: sortOrder,
      },
      success: function (response) {
        $("#gce-events-list").html(response);
      },
    });
  }

  $("#gce-apply-filters").on("click", updateEvents);
  $(".gce-filter").on("change", updateEvents); // Ajout de cette ligne pour mettre à jour en temps réel

  $("#gce-show-past-events").on("click", function () {
    $(this).hide();
    $("#gce-show-future-events").show();
    updateEvents(); // Met à jour la liste des événements avec les événements passés
  });

  // Événement pour afficher les événements futurs, et cacher le bouton correspondant
  $("#gce-show-future-events").on("click", function () {
    $(this).hide(); // Cache le bouton "Voir les événements futurs"
    $("#gce-show-past-events").show(); // Affiche le bouton "Voir les événements passés"
    updateEvents(); // Met à jour la liste des événements avec les événements futurs
  });
});
