

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
const popup = document.getElementById("registerModal");
const openRegister = document.getElementById("registerUser");
const closeBtn = document.querySelector(".close-btn");
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


//Images
function switchImage(thumbnail) {
    document.getElementById('mainImage').src = thumbnail.src;
    document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
    thumbnail.classList.add('active');
}