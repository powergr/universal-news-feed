document.addEventListener("DOMContentLoaded", function () {
  const containers = document.querySelectorAll(".lfn-container");
  if (!containers.length) {
    return;
  }
  containers.forEach(initFeedInstance);

  function initFeedInstance(container) {
    let placeholders = [];
    try {
      placeholders = JSON.parse(container.dataset.placeholders || "[]");
    } catch (e) {
      placeholders = [];
    }

    // "Load More": the items are already server-rendered in the HTML (good for SEO
    // and for visitors with JS disabled) and simply hidden via the .lfn-hidden class.
    // Clicking the button just reveals the next batch - no DOM construction needed.
    const loadMoreBtn = container.querySelector(".load-more-btn");
    if (loadMoreBtn) {
      const perPage = parseInt(loadMoreBtn.dataset.itemsPerPage, 10) || 8;
      loadMoreBtn.addEventListener("click", function () {
        const hidden = container.querySelectorAll(".news-item.lfn-hidden");
        for (let i = 0; i < perPage && i < hidden.length; i++) {
          hidden[i].classList.remove("lfn-hidden");
          hidden[i].classList.add("visible");
        }
        if (container.querySelectorAll(".news-item.lfn-hidden").length === 0) {
          loadMoreBtn.closest(".lfn-load-more-container").innerHTML = "";
        }
      });
    }

    // Broken-image fallback: some publishers block hotlinking via the Referer header,
    // so a URL that looked valid server-side can still fail to actually load in the
    // browser. Swap to a placeholder once; if that also fails, hide the image.
    container.addEventListener(
      "error",
      function (e) {
        const img = e.target;
        if (!img.classList || !img.classList.contains("news-image")) {
          return;
        }
        if (img.dataset.fallbackApplied) {
          img.style.display = "none";
          return;
        }
        if (placeholders.length > 0) {
          img.dataset.fallbackApplied = "true";
          img.src =
            placeholders[Math.floor(Math.random() * placeholders.length)];
          img.classList.add("is-placeholder");
        } else {
          img.style.display = "none";
        }
      },
      true,
    );
  }
});
