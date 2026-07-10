jQuery(document).ready(function ($) {
  $(".lfn-color-picker").wpColorPicker();
  $("#lfn-add-feed").on("click", function () {
    const container = $("#lfn-feeds-container");
    let newIndex = 0;
    container.find(".lfn-feed-row").each(function () {
      const name = $(this).find("input").first().attr("name");
      const match = name.match(/\[(\d+)\]/);
      if (match && parseInt(match[1]) >= newIndex) {
        newIndex = parseInt(match[1]) + 1;
      }
    });
    container.append(
      `<div class="lfn-feed-row"><input type="text" name="lfn_settings[rss_feeds][${newIndex}][name]" placeholder="Source Name" size="30" /><input type="url" name="lfn_settings[rss_feeds][${newIndex}][url]" placeholder="RSS Feed URL" size="50" /><button type="button" class="button lfn-remove-feed">Remove</button></div>`,
    );
  });
  $("#lfn-feeds-container").on("click", ".lfn-remove-feed", function () {
    $(this).closest(".lfn-feed-row").remove();
  });
  let mediaFrame;
  $("#lfn-add-placeholder").on("click", function (e) {
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
      const container = $("#lfn-placeholders-container");
      const attachments = mediaFrame.state().get("selection").toJSON();
      attachments.forEach(function (attachment) {
        const imageUrl = attachment.sizes.thumbnail
          ? attachment.sizes.thumbnail.url
          : attachment.url;
        container.append(
          `<div class="lfn-placeholder-item"><img src="${imageUrl}" /><input type="hidden" name="lfn_settings[placeholders][]" value="${attachment.url}"><button type="button" class="button lfn-remove-placeholder">Remove</button></div>`,
        );
      });
    });
    mediaFrame.open();
  });
  $("#lfn-placeholders-container").on(
    "click",
    ".lfn-remove-placeholder",
    function () {
      $(this).closest(".lfn-placeholder-item").remove();
    },
  );
});
