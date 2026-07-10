document.addEventListener("DOMContentLoaded", function () {
  const containers = document.querySelectorAll(".unf-container");
  if (!containers.length) {
    return;
  }
  containers.forEach(initFeedInstance);

  function initFeedInstance(container) {
    const dataScript = container.querySelector(".unf-data");
    const loadingDiv = container.querySelector(".unf-loading");
    const newsFeedDiv = container.querySelector(".unf-feed");
    const loadMoreContainer = container.querySelector(
      ".unf-load-more-container",
    );
    if (!dataScript || !newsFeedDiv) {
      return;
    }

    let feedData;
    try {
      feedData = JSON.parse(dataScript.textContent);
    } catch (e) {
      if (loadingDiv) {
        loadingDiv.textContent = "Unable to load news.";
      }
      return;
    }

    const allNews = feedData.posts || [];
    const PLACEHOLDER_URLS = feedData.placeholders || [];
    const LOAD_MORE_ENABLED = !!parseInt(feedData.load_more_enabled, 10);
    const ITEMS_PER_PAGE = parseInt(feedData.items_per_page, 10) || 8;
    let currentPage = 0;

    function renderItems(itemsToRender) {
      const fragment = document.createDocumentFragment();
      itemsToRender.forEach((news) => {
        const title = news.title || "No Title";
        const link = news.link || "#";
        const pubDate = news.pubDate || "";
        const sourceName = news.sourceName || "Unknown";
        let imageUrl = "";
        let isPlaceholder = false;

        if (news.thumbnail && news.thumbnail.startsWith("http")) {
          imageUrl = news.thumbnail;
        } else if (
          news.enclosure &&
          news.enclosure.link &&
          news.enclosure.type &&
          news.enclosure.type.startsWith("image")
        ) {
          imageUrl = news.enclosure.link;
        } else {
          const match = (news.description || "").match(
            /<img[^>]+src="([^">]+)"/,
          );
          if (match && match[1]) {
            imageUrl = match[1];
          }
        }

        if (!imageUrl && PLACEHOLDER_URLS.length > 0) {
          imageUrl =
            PLACEHOLDER_URLS[
              Math.floor(Math.random() * PLACEHOLDER_URLS.length)
            ];
          isPlaceholder = true;
        }

        const strippedDescription = (news.description || "").replace(
          /<[^>]*>/g,
          "",
        );
        const EXCERPT_LENGTH = 140;
        const descriptionText =
          strippedDescription.length > EXCERPT_LENGTH
            ? strippedDescription.substring(0, EXCERPT_LENGTH).trim() + "..."
            : strippedDescription;
        const dateString = pubDate
          ? new Date(pubDate).toLocaleString()
          : "Date not available";
        const newsItem = document.createElement("div");
        newsItem.className = "news-item";

        let imageHtml = "";
        if (imageUrl) {
          const imageClass = `news-image ${isPlaceholder ? "is-placeholder" : ""}`;
          imageHtml = `<div class="news-image-container"><a href="${link}" target="_blank" rel="nofollow noopener noreferrer"><img src="${imageUrl}" alt="${title.substring(0, 50)}" class="${imageClass}" loading="lazy" onerror="this.style.display='none'"></a></div>`;
        }

        newsItem.innerHTML = `${imageHtml}<div class="news-content"><div><div class="news-source">From: ${sourceName}</div><div class="news-title"><a href="${link}" target="_blank" rel="nofollow noopener noreferrer">${title}</a></div><p class="news-description">${descriptionText}</p></div><div class="news-footer"><span class="news-date">${dateString}</span></div></div>`;
        fragment.appendChild(newsItem);
        setTimeout(() => newsItem.classList.add("visible"), 50);
      });
      newsFeedDiv.appendChild(fragment);
    }

    function handleLoadMore() {
      currentPage++;
      renderItems(
        allNews.slice(
          currentPage * ITEMS_PER_PAGE,
          (currentPage + 1) * ITEMS_PER_PAGE,
        ),
      );
      if ((currentPage + 1) * ITEMS_PER_PAGE >= allNews.length) {
        loadMoreContainer.innerHTML = "";
      }
    }

    if (loadingDiv) {
      loadingDiv.style.display = "none";
    }
    if (allNews && allNews.length > 0) {
      if (LOAD_MORE_ENABLED) {
        renderItems(allNews.slice(0, ITEMS_PER_PAGE));
        if (allNews.length > ITEMS_PER_PAGE && loadMoreContainer) {
          const loadMoreBtn = document.createElement("button");
          loadMoreBtn.className = "load-more-btn";
          loadMoreBtn.textContent = "Load More News";
          loadMoreBtn.onclick = handleLoadMore;
          loadMoreContainer.appendChild(loadMoreBtn);
        }
      } else {
        renderItems(allNews);
      }
    } else if (loadingDiv) {
      loadingDiv.textContent = "No news available.";
      loadingDiv.style.display = "block";
    }
  }
});
