function loadStoredCart() {
  const stored = localStorage.getItem("cartItems");
  if (!stored) {
    return [];
  }

  try {
    const parsed = JSON.parse(stored);
    return Array.isArray(parsed) ? parsed : [];
  } catch (error) {
    console.warn("Data cart tidak valid, membersihkan localStorage", error);
    localStorage.removeItem("cartItems");
    return [];
  }
}

const cartItems = loadStoredCart();
const form = document.getElementById("checkout-form");
let checkoutList;
let checkoutTotalValue;
let totalHiddenInput;
let cartHiddenInput;
let currentTotal = 0;
let isSubmitting = false;

if (!form) {
  throw new Error("Checkout form tidak ditemukan.");
}

function formatCurrency(value) {
  const number = Number(value) || 0;
  return number.toLocaleString("id-ID", { minimumFractionDigits: 0 });
}

function computeLocalTotal(items) {
  return items.reduce((sum, item) => {
    const price = Number(item.price) || 0;
    const quantity = Number(item.quantity) || 0;
    return sum + price * quantity;
  }, 0);
}

function renderCartList(items) {
  if (!checkoutList) {
    return;
  }

  checkoutList.innerHTML = "";

  if (!items || items.length === 0) {
    checkoutList.innerHTML = "<li class='empty'>Keranjang kosong.</li>";
    return;
  }

  items.forEach((item) => {
    const line = document.createElement("li");
    const name = item.name ?? "Produk tidak diketahui";
    const price = Number(item.price) || 0;
    const quantity = Number(item.quantity) || 0;

    line.innerHTML = `
      <span>${name} × ${quantity}</span>
      <span>Rp ${formatCurrency(price)}</span>
    `;
    checkoutList.appendChild(line);
  });
}

function updateDisplayedTotal(amount) {
  currentTotal = Number(amount) || 0;
  if (checkoutTotalValue) {
    checkoutTotalValue.textContent = formatCurrency(currentTotal);
  }
  if (totalHiddenInput) {
    totalHiddenInput.value = currentTotal;
  }
}

async function refreshTotalFromServer(force = false) {
  if (cartItems.length === 0) {
    if (force) {
      updateDisplayedTotal(0);
    }
    return currentTotal;
  }

  try {
    const response = await fetch("../PHP/cart-summary.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ cart: cartItems }),
    });

    if (!response.ok) {
      throw new Error("Gagal menghubungkan server");
    }

    const payload = await response.json();
    const serverTotal = Number(payload.total) || 0;

    if (Array.isArray(payload.items) && payload.items.length > 0) {
      renderCartList(payload.items);
    }

    if (payload.success && serverTotal > 0) {
      updateDisplayedTotal(serverTotal);
    }
  } catch (error) {
    console.warn("Ringkasan checkout gagal", error);
  }

  return currentTotal;
}

function loadCheckoutItems() {
  checkoutList = document.getElementById("checkout-list");
  checkoutTotalValue = document.getElementById("checkout-total");
  totalHiddenInput = document.getElementById("total-hidden");
  cartHiddenInput = document.getElementById("cart-hidden");

  if (!checkoutList || !checkoutTotalValue) {
    return;
  }

  renderCartList(cartItems);
  updateDisplayedTotal(computeLocalTotal(cartItems));

  if (cartItems.length > 0) {
    refreshTotalFromServer();
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", loadCheckoutItems);
} else {
  loadCheckoutItems();
}

form.addEventListener("submit", async function (e) {
  if (isSubmitting) {
    return;
  }

  e.preventDefault();

  if (cartItems.length === 0) {
    alert("Keranjang kosong atau tidak ada barang untuk dibayar.");
    return;
  }

  await refreshTotalFromServer(true);

  if (currentTotal <= 0) {
    alert("Total tidak valid, silakan periksa kembali barang Anda.");
    return;
  }

  const nameInput = document.querySelector('input[placeholder="mr. john deo"]');
  const nameHiddenInput = document.getElementById("nama-hidden");
  if (nameHiddenInput) {
    nameHiddenInput.value = nameInput ? nameInput.value : "";
  }

  if (cartHiddenInput) {
    cartHiddenInput.value = JSON.stringify(cartItems);
  }

  if (totalHiddenInput) {
    totalHiddenInput.value = currentTotal;
  }

  isSubmitting = true;
  form.submit();
});
