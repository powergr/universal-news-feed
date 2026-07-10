jQuery(document).ready(function ($) {
  $(".unf-color-picker").wpColorPicker();
  $("#unf-add-feed").on("click", function () {
    const container = $("#unf-feeds-container");
    let newIndex = 0;
    container.find(".unf-feed-row").each(function () {
      const name = $(this).find("input").first().attr("name");
      const match = name.match(/\[(\d+)\]/);
      if (match && parseInt(match[1]) >= newIndex) {
        newIndex = parseInt(match[1]) + 1;
      }
    });
    container.append(
      `<div class="unf-feed-row"><input type="text" name="unf_settings[rss_feeds][${newIndex}][name]" placeholder="Source Name" size="30" /><input type="url" name="unf_settings[rss_feeds][${newIndex}][url]" placeholder="RSS Feed URL" size="50" /><button type="button" class="button unf-remove-feed">Remove</button></div>`,
    );
  });
  $("#unf-feeds-container").on("click", ".unf-remove-feed", function () {
    $(this).closest(".unf-feed-row").remove();
  });
  let mediaFrame;
  $("#unf-add-placeholder").on("click", function (e) {
    e.preventDefault();
    if (mediaFrame) {
      mediaFrame.open();
      return;
    }
    mediaFrame = wp.media({
      title: "Select Placeholder Images",
      button: { text: "Use these images" },
      multiple: true,
    });
    mediaFrame.on("select", function () {
      const container = $("#unf-placeholders-container");
      const attachments = mediaFrame.state().get("selection").toJSON();
      attachments.forEach(function (attachment) {
        const imageUrl = attachment.sizes.thumbnail
          ? attachment.sizes.thumbnail.url
          : attachment.url;
        container.append(
          `<div class="unf-placeholder-item"><img src="${imageUrl}" /><input type="hidden" name="unf_settings[placeholders][]" value="${attachment.url}"><button type="button" class="button unf-remove-placeholder">Remove</button></div>`,
        );
      });
    });
    mediaFrame.open();
  });
  $("#unf-placeholders-container").on(
    "click",
    ".unf-remove-placeholder",
    function () {
      $(this).closest(".unf-placeholder-item").remove();
    },
  );
});
