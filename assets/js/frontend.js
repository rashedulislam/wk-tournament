jQuery(document).ready(function ($) {
  function showSuccessMessage(message) {
    Swal.fire({
      icon: "success",
      title: "Success",
      text: message,
    });
  }

  // Function to show error message
  function showErrorMessage(message) {
    Swal.fire({
      icon: "error",
      title: "Error",
      text: message,
    });
  }

  $("#fetchTournaments").click(function () {
    Swal.showLoading();
    let tournamentID = $("#tournament-id").val();
    $.post({
      url: wkTournament.ajaxurl,
      data: {
        action: "wk_fetch_tournaments",
        _wpnonce: wkTournament.nonce,
        tournamentID: tournamentID,
      },
      success: function (response) {
        console.log(response);
        if (!response.success) {
          console.log(response.data.message);
          showErrorMessage(response.data.message);
        } else {
          showSuccessMessage(response.data.message);
          console.log("Data:", response.data);
        }
      },
    });
  });

  $("#fetchMatches").click(function () {
    Swal.showLoading();
    let tournamentID = $("#tournament-id").val();
    $.post({
      url: wkTournament.ajaxurl,
      data: {
        action: "wk_fetch_tournament_schedule",
        _wpnonce: wkTournament.nonce,
        tournamentID: tournamentID,
      },
      success: function (response) {
        console.log(response);
        if (!response.success) {
          console.log(response.data.message);
          showErrorMessage(response.data.message);
        } else {
          showSuccessMessage(response.data.message);
        }
      },
    });
  });
});
