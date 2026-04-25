const indexMessages=document.getElementById('sideBarMessagesBtn');
if (indexMessages){
    indexMessages.addEventListener("click", function(e){
      window.location.href="../communication/messages.php";
    })
}


const indexSell=document.getElementById('sideBarSellBtn');
if (indexSell){
    indexSell.addEventListener("click", function(e){
      window.location.href="../public_site/listings/listing-create-edit.php";
    })
}

const indexLogout =document.getElementById('logOutBtn');
if (indexLogout){
    indexLogout.addEventListener("click", function(e){
      window.location.href="../public_site/home/auth/logout.php";
    })
}

const togglePassword = document.getElementById('togglePassword');
const settingsPassword = document.getElementById('settingsPassword');

if (togglePassword) {
    togglePassword.addEventListener('click', function() {
        const isPassword = settingsPassword.type === 'password';
        settingsPassword.type = isPassword ? 'text' : 'password';
        this.textContent = isPassword ? '🙈' : '👁';
    });
}
const editProfile = document.getElementById('profileEditBtn');
const settingsModal = document.getElementById('settingsModal');
if (editProfile) {
    editProfile.addEventListener('click', function() {
        settingsModal.showModal();
    });
}
const darkModeToggle = document.getElementById('darkModeToggle');

// Apply saved preference on every page load
if (localStorage.getItem('darkMode') === 'true') {
    document.body.classList.add('dark-mode');
    if (darkModeToggle) darkModeToggle.checked = true;
}

if (darkModeToggle) {
    darkModeToggle.addEventListener('change', function() {
        document.body.classList.toggle('dark-mode', this.checked);
        localStorage.setItem('darkMode', this.checked);
    });
}
const reviewModal     = document.getElementById('reviewModal');
const openReviewBtn   = document.getElementById('notificationOpen'); // whatever triggers it
const closeReviewBtn  = document.getElementById('closeReview');
const reviewScore     = document.getElementById('reviewScore');
const scoreDisplay    = document.getElementById('scoreDisplay');

if (openReviewBtn) {
    openReviewBtn.addEventListener('click', () => reviewModal.showModal());
}

if (closeReviewBtn) {
    closeReviewBtn.addEventListener('click', () => reviewModal.close());
}

if (reviewScore) {
    reviewScore.addEventListener('input', function() {
        scoreDisplay.textContent = this.value + ' / 5';
    });
}
/* 
   REGISTER FORM
 */






const registerForm = document.getElementById('registerForm');

if (registerForm) {

    registerForm.addEventListener("submit", function(event) {

      
        event.preventDefault();
        let valid = true;

        const username = document.getElementById('username').value.trim();
        const email = document.getElementById('regEmail').value.trim();
        const password = document.getElementById('password').value.trim();
        const confirmPassword = document.getElementById('confirmPassword').value.trim();

        const emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2,3}$/;

        document.querySelectorAll('.error-message').forEach(msg => msg.textContent = '');

        if (username === '') {
            document.getElementById('usernameError').textContent = 'Username is required';
            valid = false;
        }

        if (email === '') {
            document.getElementById('emailError').textContent = 'Email is required';
            valid = false;
        }
        else if (!emailPattern.test(email)) {
            document.getElementById('emailError').textContent = 'Invalid email address';
            valid = false;
        }

        if (password === '') {
            document.getElementById('passwordError').textContent = 'Password is required';
            valid = false;
        }
        else if (password.length < 6) {
            document.getElementById('passwordError').textContent = 'Password must be at least 6 characters';
            valid = false;
        }

        if (confirmPassword !== password) {
            document.getElementById('confirmPasswordError').textContent = 'Passwords do not match';
            valid = false;
        }

        if (!valid) return;
     
     registerForm.removeEventListener('submit', arguments.callee);
registerForm.submit();

       

    });

}


/* 
   LOGIN
 */

const loginButton = document.getElementById('loginButton');
const loginForm = document.getElementById('loginForm');
if (loginForm) {

    loginForm.addEventListener('click', function(event) {
        
        event.preventDefault();
        const loginEmail = document.getElementById('loginEmail').value.trim();
        const loginPassword = document.getElementById('loginPassword').value.trim();

        let valid = true;
        const emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2,3}$/;

        document.querySelectorAll('.error-message').forEach(msg => msg.textContent = '');

        if (loginEmail === '') {
            document.getElementById('loginEmailError').textContent = 'Email is required';
            valid = false;
        }
        else if (!emailPattern.test(loginEmail)) {
            document.getElementById('loginEmailError').textContent = 'Invalid email';
            valid = false;
        }

        if (loginPassword === '') {
            document.getElementById('loginPasswordError').textContent = 'Password required';
            valid = false;
        }
        if (!valid) return; 
      loginForm.requestSubmit();
       
    });

}


/* 
   REGISTER POPUP
 */

// const popup = document.getElementById("registerModal");
// const openRegister = document.getElementById("registerUser");
// const registerButtonPopup = document.getElementById("registerButtonPopup");
// const closeBtn = document.querySelector(".close-btn");
// const registerMForm = document.getElementById("registerMForm");

// if (openRegister) {
//     openRegister.addEventListener("click", () => popup.showModal());
// }

// if (closeBtn) {
//     closeBtn.onclick = () => popup.close();
// }

// if (registerMForm) {

//     registerMForm.addEventListener("submit", function(event) {

//         event.preventDefault();
//          let valid = true;
//         const emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2,3}$/;

//         document.querySelectorAll('.error-message').forEach(msg => msg.textContent = '');
//         const email = document.getElementById("emailRegModal").value.trim();
        
//         const password = document.getElementById("passwordRegModal").value.trim();
//         if (email === '') {
//             document.getElementById('emailErrorM').textContent = 'Email is required';
//             valid = false;
//         }
//         else if (!emailPattern.test(email)) {
//             document.getElementById('emailErrorM').textContent = 'Invalid email';
//             valid = false;
//         }

//         if (password === '') {
//             document.getElementById('passErrorM').textContent = 'Password required';
//             valid = false;
//         }
//         if (!valid) return;
//         registerMForm.requestSubmit();
       
//     });

// }

const successPopup=document.getElementById("successModal");
const successForm=document.getElementById("successForm");
const paymentConfirmed=document.getElementById('confirmPayment');
const Successclose=document.getElementById('closeSuccess')

if (paymentConfirmed){
document.getElementById("confirmPayment").addEventListener("click", () => {
    localStorage.setItem("paymentSuccess", "true");
    window.location.href = "success.php";

});}if (localStorage.getItem("paymentSuccess") === "true") {
    successPopup.showModal();
    localStorage.removeItem("paymentSuccess"); // prevent repeat
}

const popup = document.getElementById("registerModal");
const openRegister = document.getElementById("registerUser");
const closeBtn = document.getElementById('close-btn');
const registerMForm = document.getElementById("registerMForm");


if (openRegister) {
    openRegister.addEventListener("click", () => popup.showModal());
}

if (closeBtn) {
    closeBtn.onclick = () => popup.close();
    
}

if (registerMForm) {
    registerMForm.addEventListener("click", function(event) {
        event.preventDefault();

        let valid = true;
        const emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2,3}$/;

        document.querySelectorAll('.error-message').forEach(msg => msg.textContent = '');

        const email    = document.getElementById("emailRegModal").value.trim();
        const password = document.getElementById("passwordRegModal").value.trim();

        if (email === '') {
            document.getElementById('emailErrorM').textContent = 'Email is required';
            valid = false;
        } else if (!emailPattern.test(email)) {
            document.getElementById('emailErrorM').textContent = 'Invalid email';
            valid = false;
        }

        if (password === '') {
            document.getElementById('passErrorM').textContent = 'Password is required';
            valid = false;
        } else if (password.length < 6) {
            document.getElementById('passErrorM').textContent = 'At least 6 characters';
            valid = false;
        }

        if (!valid) return;
        
        registerMForm.requestSubmit();
     
    });
}

/* 
   ACCOUNT MODAL
 */

const accViewPopup = document.getElementById("viewAccount");
const accPopup = document.getElementById("accountModal");

if (accViewPopup) {

    accViewPopup.addEventListener("click", () => {
        accPopup.showModal();
    });

}

if (accPopup) {

    accPopup.addEventListener("click", function(event) {

        if (event.target === accPopup) {
            accPopup.close();
        }

    });

}


/* 
   PRODUCT LOADING (SIMULATED)
 

const imageFiles = [
    "confusedcat.png",
    "oldiecat.jpeg",
    "thatsenoughcat.jpeg",
    "vapingcat.jpeg",
    "zootedcat.jpeg"
];

const products = [];

for (let i = 1; i <= 6; i++) {

    products.push({
        name: `Product ${i}`,
        price: i * 10,
        image: `img/${imageFiles[i % imageFiles.length]}`
    });

}

let itemsPerLoad = 4;
let loaded = 0;

const container = document.getElementById('product-section');

function loadItems() {

    const slice = products.slice(loaded, loaded + itemsPerLoad);

    slice.forEach(product => {

        const card = document.createElement('div');
        card.className = 'product-card';

        card.innerHTML = `
        <img src="${product.image}" alt="${product.name}">
        <h3>${product.name}</h3>
        <p>$${product.price}</p>
        `;

        container.appendChild(card);

    });

    loaded += slice.length;

}

if (container) {

    loadItems();

    window.addEventListener('scroll', () => {

        if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500) {

            if (loaded < products.length) {
                loadItems();
            }

        }

    });

}
*/
// NOTIFICATIONS

const noti = document.getElementById("notificationIcon");
if (noti){
    noti.addEventListener('click',function(event){
        accPopup.showModal();
    })
}

// GRADIENTS
document.addEventListener('mousemove', (e) => {
      const x = (e.clientX / window.innerWidth) * 100 + '%';
      const y = (e.clientY / window.innerHeight) * 100 + '%';

      document.documentElement.style.setProperty('--mouse-x', x);
      document.documentElement.style.setProperty('--mouse-y', y);
}
);

// SETTINGS


document.addEventListener('DOMContentLoaded', function() {
    const box    = document.getElementById("uploadBox");
    const input  = document.getElementById("fileInput");
    const thumbs = document.getElementById("thumbs");
    if (!box || !input || !thumbs) return;

    const keptInput    = document.getElementById("keptImages");
    const keptVal      = keptInput ? keptInput.value.trim() : '';
let existingImages = keptVal !== '' ? keptVal.split('#').filter(Boolean) : [];
    let selectedFiles  = [];

    // ── Open picker when clicking the box (not the remove button) ──
    box.addEventListener("click", (e) => {
        if (e.target.tagName === "BUTTON" || e.target === input|| e.target ===thumbs) return;
        input.click();
    });

    box.addEventListener("dragover", (e) => {
        e.preventDefault();
        box.classList.add("dragover");
    });

    box.addEventListener("dragleave", () => {
        box.classList.remove("dragover");
    });

    box.addEventListener("drop", (e) => {
        e.preventDefault();
        box.classList.remove("dragover");
        selectedFiles = selectedFiles.concat(Array.from(e.dataTransfer.files));
        render();
    });

    input.addEventListener("change", (e) => {
        selectedFiles = selectedFiles.concat(Array.from(e.target.files));
        render();
    });

    // ── Sync before submit ──
   const form = document.getElementById("createListingForm");
if (form) {
    form.addEventListener("submit", () => {
        const dt = new DataTransfer();
        selectedFiles.forEach(f => dt.items.add(f));
        input.files = dt.files;
        if (keptInput) keptInput.value = existingImages.join('#');
    });
}

    // ── Render all images ──
    function render() {
        thumbs.innerHTML = "";

        const allExisting = existingImages.map(name  => ({ type: 'existing', name }));
        const allNew      = selectedFiles.map((file, i) => ({ type: 'new', file, i }));
        const all         = [...allExisting, ...allNew];

        if (all.length === 0) {
            // Nothing uploaded yet — restore placeholder
            box.innerHTML = `<span class="upload-text">Click or drop images</span>`;
            return;
        }

        // Resolve all srcs first so order is preserved, then place them
        const srcs = new Array(all.length);
        let resolved = 0;

        all.forEach((item, pos) => {
            if (item.type === 'existing') {
                srcs[pos] = { src: `../img/${item.name}`, item, pos };
                resolved++;
                if (resolved === all.length) placeAll(srcs);
            } else {
                const reader = new FileReader();
                reader.onload = (e) => {
                    srcs[pos] = { src: e.target.result, item, pos };
                    resolved++;
                    if (resolved === all.length) placeAll(srcs);
                };
                reader.readAsDataURL(item.file);
            }
        });
        console.log()
    }

    function placeAll(srcs) {
        box.innerHTML = "";
        thumbs.innerHTML = "";

        srcs.forEach(({ src, item, pos }) => {
            if (pos === 0) {
                placeMain(src, item);
            } else {
                placeThumb(src, item, pos);
            }
        });
    }

    // ── Main image box ──
    function placeMain(src, item) {
        const img = document.createElement("img");
        img.src = src;
        img.style.cssText = "width:100%; height:100%; object-fit:cover; pointer-events:none;";

        const removeBtn = makeRemoveBtn(() => removeItem(item));
        removeBtn.style.top   = "8px";
        removeBtn.style.right = "8px";

        box.appendChild(img);
        box.appendChild(removeBtn);
    }

    // ── Thumbnail ──
    function placeThumb(src, item, pos) {
        const wrap = document.createElement("div");
        wrap.style.cssText = "position:relative; display:inline-block; flex-shrink:0;";

        const img = document.createElement("img");
        img.src = src;
        img.style.cssText = "width:70px; height:70px; object-fit:cover; border-radius:6px; display:block; cursor:pointer;";

        // ── Swap thumbnail with main image ──
        img.addEventListener("click", () => {
            const mainImg = box.querySelector("img");
            if (!mainImg) return;

            // Swap srcs visually
            const tempSrc = mainImg.src;
            

            // Swap in data arrays so order is preserved on submit
            const mainIsExisting = tempSrc.includes('../img/');
            if (item.type === 'existing') {
                // Move this existing image to front
                existingImages = existingImages.filter(n => n !== item.name);
               
                existingImages.unshift(item.name);
                 const removeBtn = makeRemoveBtn(() => removeItem(item));
                 render();
            } else {
                // Move this new file to front of selectedFiles
                selectedFiles = selectedFiles.filter((_, idx) => idx !== item.i);
                selectedFiles.unshift(item.file);
                // Re-index items
            }
           
        });

       const removeBtn = makeRemoveBtn(() => removeItem(item));

        wrap.appendChild(img);
        wrap.appendChild(removeBtn);
        thumbs.appendChild(wrap);
    }

    // ── Remove an item and re-render ──
    function removeItem(item) {
        if (item.type === 'existing') {
            existingImages = existingImages.filter(n => n !== item.name);
            
        } else {
            selectedFiles = selectedFiles.filter((_, idx) => idx !== item.i);
        }
        render();
    }

    // ── Build a remove button ──
    function makeRemoveBtn(onClick) {
        const btn = document.createElement("button");
        btn.type      = "button";
        btn.innerHTML = "&#10005;";
        btn.style.cssText = `
            position: absolute;
            top: 4px;
            right: 4px;
            background: rgba(0,0,0,0.55);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 11px;
            cursor: pointer;
            line-height: 1;
            z-index: 10;
        `;
        btn.addEventListener("click", (e) => {
            e.stopPropagation();
            onClick();
        });
        return btn;
    }

    // Draw existing images on page load
    render();

    // ── Type select toggle ──
    const typeSelect     = document.getElementById("type");
    const productSection = document.getElementById("amount");
    if (typeSelect && productSection) {
        typeSelect.addEventListener("change", function() {
            productSection.style.display = this.value === "product" ? "flex" : "none";
        });
    }
});function switchImage(thumbnail) {
    document.getElementById('mainImage').src = thumbnail.src;
    document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
    thumbnail.classList.add('active');
}