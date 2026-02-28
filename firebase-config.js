import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js";
import { getDatabase } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-database.js";

const firebaseConfig = {
  apiKey: "AIzaSyA2YJXgAZAjOVqZ3eeTiMa9kCKaut_cA2w",
  authDomain: "lore-basedatos.firebaseapp.com",
  databaseURL: "https://lore-basedatos-default-rtdb.firebaseio.com",
  projectId: "lore-basedatos",
  storageBucket: "lore-basedatos.firebasestorage.app",
  messagingSenderId: "348470598297",
  appId: "1:348470598297:web:4634471f293f24a64fb608"
};

const app = initializeApp(firebaseConfig);
export const db = getDatabase(app);