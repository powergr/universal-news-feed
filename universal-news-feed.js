document.addEventListener("DOMContentLoaded", function () {
  const containers = document.querySelectorAll(".lfn-container");
  if (!containers.length) { return; }
  containers.forEach(initFeedInstance);

  // Only allow http(s) URLs through to href/src. Feed content is untrusted, so this
  // blocks things like javascript: URIs sneaking in via a malicious or compromised feed.
  function isSafeUrl(url) {
    try {
      const parsed = new URL(url, window.location.origin);
      return parsed.protocol === "http:" || parsed.protocol === "https:";
    } catch (e) {
      return false;
    }
  }

  function parseDate(pubDate, notAvailableText) {
    if (!pubDate) { return notAvailableText; }
    const d = new Date(pubDate);
    return isNaN(d.getTime()) ? notAvailableText : d.toLocaleString();
  }

  function initFeedInstance(container) {
    const dataScript = container.querySelector(".lfn-data");
    const loadingDiv = container.querySelector(".lfn-loading");
    const newsFeedDiv = container.querySelector(".lfn-feed");
    const loadMoreContainer = container.querySelector(".lfn-load-more-container");
    if (!dataScript || !newsFeedDiv) { return; }

    let feedData;
    try {
      feedData = JSON.parse(dataScript.textContent);
    } catch (e) {
      if (loadingDiv) { loadingDiv.textContent = "Unable to load news."; }
      return;
    }

    const i18n = feedData.i18n || {};

    const allNews = feedData.posts || [];
    const PLACEHOLDER_URLS = feedData.placeholders || [];
    const LOAD_MORE_ENABLED = !!parseInt(feedData.load_more_enabled, 10);
    const ITEMS_PER_PAGE = parseInt(feedData.items_per_page, 10) || 8;
    let currentPage = 0;

    function buildNewsItem(news) {
      const title = news.title || i18n.no_title || "No Title";
      const rawLink = news.link || "#";
      const link = rawLink === "#" || isSafeUrl(rawLink) ? rawLink : "#";
      const sourceName = news.sourceName || i18n.unknown_source || "Unknown";
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
        const match = (news.description || "").match(/<img[^>]+src="([^">]+)"/);
        if (match && match[1]) {
          imageUrl = match[1];
        }
      }
      if (imageUrl && !isSafeUrl(imageUrl)) { imageUrl = ""; }

      if (!imageUrl && PLACEHOLDER_URLS.length > 0) {
        imageUrl =
          PLACEHOLDER_URLS[Math.floor(Math.random() * PLACEHOLDER_URLS.length)];
        isPlaceholder = true;
      }

      const strippedDescription = (news.description || "").replace(/<[^>]*>/g, "");
      const EXCERPT_LENGTH = 140;
      const descriptionText =
        strippedDescription.length > EXCERPT_LENGTH
          ? strippedDescription.substring(0, EXCERPT_LENGTH).trim() + "..."
          : strippedDescription;
      const dateString = parseDate(news.pubDate, i18n.date_not_available || "Date not available");

      const newsItem = document.createElement("div");
      newsItem.className = "news-item";

      if (imageUrl) {
        const imageContainer = document.createElement("div");
        imageContainer.className = "news-image-container";
        const imageLink = document.createElement("a");
        imageLink.href = link;
        imageLink.target = "_blank";
        imageLink.rel = "nofollow noopener noreferrer";
        imageLink.tabIndex = -1;
        imageLink.setAttribute("aria-hidden", "true");
        const img = document.createElement("img");
        img.src = imageUrl;
        img.alt = title.substring(0, 50);
        img.className = `news-image ${isPlaceholder ? "is-placeholder" : ""}`;
        img.loading = "lazy";
        img.addEventListener("error", function () {
          // The URL we had looked valid, but the browser couldn't actually load it (common
          // with publishers that block hotlinking via the Referer header). Fall back to a
          // placeholder once; if the placeholder itself also fails, give up and hide it.
          if (this.dataset.fallbackApplied) {
            this.style.display = "none";
            return;
          }
          if (PLACEHOLDER_URLS.length > 0) {
            this.dataset.fallbackApplied = "true";
            this.src = PLACEHOLDER_URLS[Math.floor(Math.random() * PLACEHOLDER_URLS.length)];
            this.classList.add("is-placeholder");
          } else {
            this.style.display = "none";
          }
        });
        imageLink.appendChild(img);
        imageContainer.appendChild(imageLink);
        newsItem.appendChild(imageContainer);
      }

      const contentDiv = document.createElement("div");
      contentDiv.className = "news-content";

      const topGroup = document.createElement("div");

      const sourceDiv = document.createElement("div");
      sourceDiv.className = "news-source";
      sourceDiv.textContent = `${i18n.from_prefix || "From:"} ${sourceName}`;
      topGroup.appendChild(sourceDiv);

      const titleDiv = document.createElement("div");
      titleDiv.className = "news-title";
      const titleLink = document.createElement("a");
      titleLink.href = link;
      titleLink.target = "_blank";
      titleLink.rel = "nofollow noopener noreferrer";
      titleLink.textContent = title;
      titleDiv.appendChild(titleLink);
      topGroup.appendChild(titleDiv);

      const descP = document.createElement("p");
      descP.className = "news-description";
      descP.textContent = descriptionText;
      topGroup.appendChild(descP);

      contentDiv.appendChild(topGroup);

      const footerDiv = document.createElement("div");
      footerDiv.className = "news-footer";
      const dateSpan = document.createElement("span");
      dateSpan.className = "news-date";
      dateSpan.textContent = dateString;
      footerDiv.appendChild(dateSpan);
      contentDiv.appendChild(footerDiv);

      newsItem.appendChild(contentDiv);
      return newsItem;
    }

    function renderItems(itemsToRender) {
      const fragment = document.createDocumentFragment();
      itemsToRender.forEach((news) => {
        const newsItem = buildNewsItem(news);
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

    if (loadingDiv) { loadingDiv.style.display = "none"; }
    if (allNews && allNews.length > 0) {
      if (LOAD_MORE_ENABLED) {
        renderItems(allNews.slice(0, ITEMS_PER_PAGE));
        if (allNews.length > ITEMS_PER_PAGE && loadMoreContainer) {
          const loadMoreBtn = document.createElement("button");
          loadMoreBtn.className = "load-more-btn";
          loadMoreBtn.textContent = i18n.load_more || "Load More News";
          loadMoreBtn.onclick = handleLoadMore;
          loadMoreContainer.appendChild(loadMoreBtn);
        }
      } else {
        renderItems(allNews);
      }
    } else if (loadingDiv) {
      loadingDiv.textContent = i18n.no_news_available || "No news available.";
      loadingDiv.style.display = "block";
    }
  }
});