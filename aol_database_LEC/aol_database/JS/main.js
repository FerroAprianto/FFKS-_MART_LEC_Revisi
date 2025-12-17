
function formatRupiah(angka) {
    return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}


let cartItems = JSON.parse(localStorage.getItem('cartItems')) || [];
let total = 0;
let itemCount = 0;


function addToCart(productCard) {
    const id = productCard.dataset.id;
    const name = productCard.querySelector('.product-name').textContent;
    const imgsrc = productCard.querySelector('.product-img').src;
    

    const priceText = productCard.querySelector('.product-price').textContent;
    const price = parseInt(priceText.replace(/[^\d]/g, '')); 

    const existingItem = cartItems.find((item) => item.id === id);

    if(existingItem){
        existingItem.quantity += 1;
    } else {
        cartItems.push({
            id: id,
            name: name,
            price: price,
            original_price: price, 
            quantity: 1,
            image: imgsrc,
        });
    }

    updateLocalStorage();
    updateCartDisplay();
    
  
    document.querySelector('.cart-model').classList.add('open-cart');
}


function removeItem(id){
    cartItems = cartItems.filter((item) => item.id !== id);
    updateLocalStorage();
    updateCartDisplay();
}


function changeQuantity(id, delta){
    const item = cartItems.find((item) => item.id === id);
    if(item){
        item.quantity += delta;
        if(item.quantity <= 0){
            removeItem(id);
        }else{
            updateLocalStorage();
            updateCartDisplay();
        }
    }
}


function updateCartDisplay(){
    const cartList = document.getElementById('cart-items');
    const totalElement = document.getElementById('total-price');
    const countElement = document.getElementById('cart-count');

    if(!cartList) return; 

    cartList.innerHTML = '';
    total = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    itemCount = cartItems.reduce((count, item) => count + item.quantity, 0);

    cartItems.forEach((item) => {
        const li = document.createElement('li');
        li.classList.add('cart-item');
        
        li.style.cssText = "display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee;";

        li.innerHTML = `
            <div style="display:flex; align-items:center; gap:10px;">
                <img src="${item.image}" style="width: 50px; height: 50px; object-fit: cover; border-radius:5px;">
                <div>
                    <div style="font-size: 0.9rem; font-weight: bold;">${item.name}</div>
                    <div style="color: #1eb853; font-size: 0.85rem;">${formatRupiah(item.price)} x ${item.quantity}</div>
                </div>
            </div>
            
            <div style="display: flex; align-items: center; gap: 5px;">
                <button onclick="changeQuantity('${item.id}', -1)" style="width:25px; height:25px; cursor:pointer;">-</button>
                <button onclick="changeQuantity('${item.id}', 1)" style="width:25px; height:25px; cursor:pointer;">+</button>
                <i class='bx bx-trash' onclick="removeItem('${item.id}')" style="margin-left: 5px; color: red; cursor: pointer;"></i>
            </div>
        `;
        cartList.appendChild(li);
    });

    if(totalElement) totalElement.textContent = formatRupiah(total);
    if(countElement) countElement.textContent = itemCount;
}


function updateLocalStorage(){
    localStorage.setItem('cartItems', JSON.stringify(cartItems));
}


document.addEventListener('DOMContentLoaded', () => {
    updateCartDisplay(); // Load cart saat halaman dibuka

 
    const checkoutBtn = document.getElementById('checkout-btn');
    if(checkoutBtn){
        checkoutBtn.addEventListener('click', function() {
           
            if (cartItems.length === 0) {
                alert("Keranjang belanja kosong!");
                return;
            }
            
        
            updateLocalStorage(); 
            
           
            window.location.href = "checkout.php"; 
        });
    }

   
    const cartIcon = document.querySelector('#cart-icon');
    const cartModel = document.querySelector('.cart-model');
    const cartClose = document.querySelector('.close-btn');

    if(cartIcon) {
        cartIcon.onclick = () => { cartModel.classList.add('open-cart'); };
    }
    if(cartClose) {
        cartClose.onclick = () => { cartModel.classList.remove('open-cart'); };
    }

   
    const kategoriSelect = document.getElementById("kategoriSelect");
    if (kategoriSelect) {
        kategoriSelect.addEventListener("change", function() {
            const selected = this.value;
            const products = document.querySelectorAll(".shop-container .box");
            products.forEach(p => {
                const kat = p.getAttribute("data-kategori") || "null";
                p.style.display = (selected === "all" || selected === kat) ? "flex" : "none";
            });
        });
    }
});

// --- MENU MOBILE ---
let menu = document.querySelector('#menu-icon');
let navbar = document.querySelector('.navbar');
if(menu){
    menu.onclick = () => {
        menu.classList.toggle('bx-x');
        navbar.classList.toggle('active');
    }
    window.onscroll = () => {
        menu.classList.remove('bx-x');
        navbar.classList.remove('active');
    }
}