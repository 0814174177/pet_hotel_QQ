(function () {
  'use strict';

  const POLLING_INTERVAL_MS = 5000;

  document.addEventListener('DOMContentLoaded', function () {
    const config = readPaymentConfig();

    bindPaymentMethods();
    bindCouponPreview(config);
    startStatusPolling(config);
  });

  function readPaymentConfig() {
    const configNode = document.getElementById('payment-page-config');

    if (!configNode) {
      return {};
    }

    try {
      return JSON.parse(configNode.textContent || '{}');
    } catch (error) {
      console.error('Cannot parse payment config:', error);
      return {};
    }
  }

  function bindPaymentMethods() {
    const paymentMethods = document.querySelectorAll('.payment-method');

    paymentMethods.forEach(function (method) {
      method.addEventListener('click', function () {
        paymentMethods.forEach(function (item) {
          item.classList.remove('active');
        });

        method.classList.add('active');

        const radio = method.querySelector('input[type="radio"]');

        if (radio) {
          radio.checked = true;
        }
      });
    });
  }

  function bindCouponPreview(config) {
    const input = document.querySelector('[data-coupon-input]');
    const button = document.querySelector('[data-apply-coupon]');

    if (!input || !button || !config.applyCouponUrl) {
      return;
    }

    button.addEventListener('click', function () {
      applyCouponPreview(config, input, button);
    });

    input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        applyCouponPreview(config, input, button);
      }
    });
  }

  async function applyCouponPreview(config, input, button) {
    const couponCode = input.value.trim();

    if (!couponCode) {
      showCouponMessage('Nhap ma giam gia truoc khi ap dung.', 'error');
      input.focus();
      return;
    }

    setCouponLoading(button, true);
    showCouponMessage('', '');

    try {
      const response = await fetch(config.applyCouponUrl, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': config.csrfToken || csrfTokenFromMeta(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          coupon_code: couponCode,
        }),
      });

      const data = await response.json().catch(function () {
        return null;
      });

      if (response.status === 401) {
        reloadPaymentPage('Phien dang nhap da het han. Trang se duoc tai lai.');
        return;
      }

      if (response.status === 404 || (data && data.exists === false)) {
        redirectToHistory(config, 'Don hang khong con ton tai tren he thong.');
        return;
      }

      if (response.status === 422) {
        showCouponMessage(firstValidationMessage(data) || 'Ma giam gia khong hop le.', 'error');
        return;
      }

      if (!response.ok || !data || data.exists !== true || !data.payment) {
        showCouponMessage('Khong the ap dung ma giam gia. Vui long thu lai.', 'error');
        return;
      }

      updatePaymentTotals(data.payment);
      showCouponMessage(data.payment.message || 'Ma giam gia da duoc ap dung.', 'success');
    } catch (error) {
      console.error('Coupon preview error:', error);
      showCouponMessage('Khong the ket noi may chu. Vui long thu lai.', 'error');
    } finally {
      setCouponLoading(button, false);
    }
  }

  function updatePaymentTotals(payment) {
    const discountAmount = Number(payment.discount_amount || 0);
    const grandTotal = Number(payment.grand_total || 0);
    const discountNode = document.querySelector('[data-discount-amount]');
    const grandTotalNode = document.querySelector('[data-grand-total]');

    if (discountNode) {
      discountNode.textContent = '-' + formatMoney(discountAmount);
    }

    if (grandTotalNode) {
      grandTotalNode.textContent = formatMoney(grandTotal);
    }
  }

  function showCouponMessage(message, type) {
    const messageNode = document.querySelector('[data-coupon-message]');

    if (!messageNode) {
      return;
    }

    messageNode.textContent = message;
    messageNode.classList.remove('success', 'error');

    if (!message) {
      messageNode.hidden = true;
      return;
    }

    messageNode.hidden = false;

    if (type) {
      messageNode.classList.add(type);
    }
  }

  function setCouponLoading(button, isLoading) {
    button.disabled = isLoading;
    button.dataset.originalText = button.dataset.originalText || button.textContent;
    button.textContent = isLoading ? 'Dang ap dung...' : button.dataset.originalText;
  }

  function startStatusPolling(config) {
    if (!config.checkStatusUrl) {
      return;
    }

    let pollingStopped = false;
    let requestInFlight = false;
    let pollingInterval = null;
    const currentStatus = String(config.currentStatus || '');
    const serverGrandTotal = Number(config.serverGrandTotal || 0);

    function stopPolling() {
      pollingStopped = true;

      if (pollingInterval) {
        clearInterval(pollingInterval);
      }
    }

    async function fetchOrderStatus() {
      if (
        pollingStopped ||
        requestInFlight ||
        document.visibilityState !== 'visible'
      ) {
        return;
      }

      requestInFlight = true;

      try {
        const response = await fetch(config.checkStatusUrl, {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        });

        const data = await response.json().catch(function () {
          return null;
        });

        if (response.status === 401) {
          reloadPaymentPage('Phien dang nhap da het han. Trang se duoc tai lai.');
          return;
        }

        if (response.status === 404 || (data && data.exists === false)) {
          stopPolling();
          lockPaymentForm();
          redirectToHistory(config, 'Don hang khong con ton tai tren he thong.');
          return;
        }

        if (!response.ok || !data || data.exists !== true) {
          return;
        }

        if (String(data.status || '') !== currentStatus) {
          reloadPaymentPage('Trang thai don hang da thay doi. Trang se tai lai de cap nhat.');
          return;
        }

        const nextTotal = Number(data.grand_total);

        if (!Number.isNaN(nextTotal) && Math.abs(nextTotal - serverGrandTotal) >= 0.01) {
          reloadPaymentPage('Tong tien don hang vua duoc cap nhat. Vui long kiem tra lai truoc khi thanh toan.');
        }
      } catch (error) {
        console.error('Payment status polling error:', error);
      } finally {
        requestInFlight = false;
      }
    }

    pollingInterval = setInterval(fetchOrderStatus, POLLING_INTERVAL_MS);

    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') {
        fetchOrderStatus();
      }
    });

    window.addEventListener('beforeunload', stopPolling);
  }

  function reloadPaymentPage(message) {
    lockPaymentForm();
    alert(message);
    window.location.reload();
  }

  function redirectToHistory(config, message) {
    alert(message);
    window.location.href = config.historyUrl || '/profile/history-booking';
  }

  function lockPaymentForm() {
    document
      .querySelectorAll('.payment-layout button, .payment-layout input, .payment-layout select, .payment-layout textarea')
      .forEach(function (control) {
        control.disabled = true;
      });
  }

  function firstValidationMessage(data) {
    if (!data || !data.errors) {
      return data && data.message ? data.message : '';
    }

    const firstKey = Object.keys(data.errors)[0];
    const firstError = firstKey ? data.errors[firstKey] : null;

    return Array.isArray(firstError) ? firstError[0] : firstError;
  }

  function csrfTokenFromMeta() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    return meta ? meta.getAttribute('content') : '';
  }

  function formatMoney(amount) {
    const value = Number(amount || 0);

    return new Intl.NumberFormat('vi-VN').format(value) + '\u0111';
  }
})();
