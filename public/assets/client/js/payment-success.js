(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', initPaymentSuccessPage);

  function initPaymentSuccessPage() {
    const page = document.querySelector('.ps-wrapper');

    if (!page) {
      return;
    }

    preventBackNavigation();
    exposeClipboardHelper();
    bindCopyActions();
    pushEcommerceData(readTrackingData());

    window.setTimeout(triggerSuccessConfetti, 300);
  }

  function preventBackNavigation() {
    window.history.pushState(null, '', window.location.href);

    window.addEventListener('popstate', function () {
      window.history.pushState(null, '', window.location.href);
    });
  }

  function exposeClipboardHelper() {
    window.copyToClipboard = copyToClipboard;
  }

  function bindCopyActions() {
    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
      button.addEventListener('click', function () {
        copyToClipboard(button.dataset.copyTarget, button);
      });

      button.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          copyToClipboard(button.dataset.copyTarget, button);
        }
      });
    });
  }

  async function copyToClipboard(elementId, triggerElement) {
    const textToCopy = textFromElement(elementId);

    if (!textToCopy) {
      return;
    }

    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(textToCopy);
      } else {
        fallbackCopyToClipboard(textToCopy);
      }

      showCopyFeedback(triggerElement, textToCopy);
    } catch (error) {
      console.error('Copy to clipboard failed:', error);
      fallbackCopyToClipboard(textToCopy);
      showCopyFeedback(triggerElement, textToCopy);
    }
  }

  function textFromElement(elementId) {
    const element = document.getElementById(elementId);

    if (!element) {
      return '';
    }

    return element.innerText.replace('#', '').trim();
  }

  function fallbackCopyToClipboard(textToCopy) {
    const textArea = document.createElement('textarea');

    textArea.value = textToCopy;
    textArea.setAttribute('readonly', '');
    textArea.style.position = 'fixed';
    textArea.style.opacity = '0';
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    textArea.remove();
  }

  function showCopyFeedback(triggerElement, textToCopy) {
    if (triggerElement) {
      triggerElement.classList.add('is-copied');

      window.setTimeout(function () {
        triggerElement.classList.remove('is-copied');
      }, 900);
    }

    window.alert('Da sao chep ma: ' + textToCopy);
  }

  function readTrackingData() {
    const dataElement = document.getElementById('payment-success-data');

    if (!dataElement) {
      return null;
    }

    try {
      return JSON.parse(dataElement.textContent || '{}');
    } catch (error) {
      console.error('Cannot parse payment success tracking data:', error);
      return null;
    }
  }

  function pushEcommerceData(transactionData) {
    if (!transactionData) {
      return;
    }

    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: 'purchase',
      ecommerce: transactionData,
    });

    console.log('Payment transaction tracked.', transactionData);
  }

  function triggerSuccessConfetti() {
    if (typeof window.confetti !== 'function') {
      return;
    }

    const duration = 3000;
    const end = Date.now() + duration;
    const colors = ['#c9954a', '#7a9e7e', '#f4a6a6'];

    (function frame() {
      window.confetti({
        particleCount: 5,
        angle: 60,
        spread: 55,
        origin: { x: 0 },
        colors: colors,
      });

      window.confetti({
        particleCount: 5,
        angle: 120,
        spread: 55,
        origin: { x: 1 },
        colors: colors,
      });

      if (Date.now() < end) {
        window.requestAnimationFrame(frame);
      }
    }());
  }
})();
