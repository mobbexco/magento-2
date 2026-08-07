define(['jquery'], function ($) {
  'use strict';

  return function (config) {
    $(document).ready(function () {
      const productIds = getProductIdsOnPage();

      if (productIds.length === 0) {
        return;
      }

      $.ajax({
        url: config.financeDataUrl,
        method: 'POST',
        data: { product_ids: productIds },
        success: function (response) {
          if (response.products) {
            displayProductTags(response.products);
          }
        },
        error: function () {
          console.error('Mobbex: Error fetching finance data.');
        }
      });
    });

    /**
     * Scans the page to find all product items and extracts their IDs.
     */
    function getProductIdsOnPage() {
      const productIds = new Set();
      const priceBoxes = document.querySelectorAll('.price-box[data-product-id]'); 
     
      priceBoxes.forEach((priceBox) => {
        const productId = priceBox.dataset.productId;
        if (productId) {
          productIds.add(productId);
        }
      });

      return Array.from(productIds);
    }

    /**
     * Iterates through all products with financing and displays their tags.
     */
    function displayProductTags(financeData) {
      const anchorProp = {
        price: ".price-box",
        list: ".product-items",
        img: ".product-image-container",
      };

      for (const productId in financeData) {
        if (Object.hasOwnProperty.call(financeData, productId)) {
          const plan = financeData[productId];
          // Find product element(s) on the page
          const productElements = document.querySelectorAll('.price-box[data-product-id="' + productId + '"]');
          
          productElements.forEach(priceBox => {
            const productItem = priceBox.closest('li.product-item, .product-info-main');
            if (productItem && !productItem.querySelector('.mobbex-product-banner')) {
                if (config.showTag) addSourceTag(productItem, anchorProp.img, plan);
                if (config.showBanner) addFinanceBanner(productItem, anchorProp.price, plan);
            }
          });
        }
      }
    }

    // Handles add tag over product image
    function addSourceTag(product, eImg, plan) {
      const imgElement = product.querySelector(eImg);
      if (!imgElement || imgElement.querySelector('.mobbex-wrapper')) return;

      const wrapper = document.createElement("div");
      wrapper.classList.add("mobbex-wrapper");

      const flagBody = document.createElement("div");
      flagBody.classList.add("mobbex-flag");
      flagBody.innerHTML = `
        <div class="mobbex-flag-top">
          <span class='mobbex-flag-top-count' style='font-size:${plan.plan_count < 10 ? "2.5" : "1.85"}rem'>${plan.plan_count}</span>
          <span class='mobbex-flag-top-text'>${financeText(plan.plan_percentage).replace(' ', "<br>")}</span>
        </div>
        <div class="mobbex-flag-bottom">
          <span class='mobbex-flag-bottom-source'>Con ${plan.plan_source}</span>
        </div>`;

      // Wrap image to position the flag
      imgElement.parentNode.insertBefore(wrapper, imgElement);
      wrapper.appendChild(imgElement);
      wrapper.appendChild(flagBody);
    }

    // Handles add banner
    function addFinanceBanner(product, ePrice, plan) {
      const priceElement = product.querySelector(ePrice);
      if (!priceElement) return;

      const banner = document.createElement("div");
      banner.classList.add("mobbex-product-banner");

      const bannerTop = document.createElement("div");
      bannerTop.classList.add("mobbex-product-banner-top");
      bannerTop.innerHTML = (plan.plan_count > 1)
        ? `<span class='mobbex-installment-span-left'>Hasta</span><span class='mobbex-installment-span-right'>${plan.plan_count} Cuotas</span>`
        : `<span class='mobbex-installment-span-left'>En</span><span class='mobbex-installment-span-right'>${plan.plan_count} Pago</span>`;

      const bannerBottom = document.createElement("div");
      bannerBottom.classList.add("mobbex-product-banner-bottom");
      bannerBottom.innerHTML = `${financeText(plan.plan_percentage)} de $${plan.plan_amount}`;

      banner.appendChild(bannerTop);
      banner.appendChild(bannerBottom);

      priceElement.parentNode.insertBefore(banner, priceElement.nextSibling);
    }

    function financeText(percentage) {
      if (percentage == 0) return "Sin interés";
      if (percentage < 0) return "Con descuento";
      if (percentage > 0) return "Con interés";
      return "";
    }
  };
});