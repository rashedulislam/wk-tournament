jQuery(document).ready(function ($) {
  $("#fetchTournaments").click(function () {
    $.post({
      url: wkTournament.ajaxurl,
      data: {
        action: "wk_fetch_tournaments",
        _wpnonce: wkTournament.nonce,
      },
      success: function (response) {
        console.log(response);
        if (!response.success) {
          console.log(response.data.message);
          alert(response.data.message);
        } else {
          console.log("Data:", response.data);
        }
      },
    });
  });
});
