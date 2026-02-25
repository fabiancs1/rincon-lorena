// ============================================================
//  CONFIGURACIÓN DE FIREBASE — Rincón de Lorena
//  INSTRUCCIÓN: Reemplaza los valores de abajo con los de tu
//  proyecto en Firebase Console → Configuración → Tus apps
// ============================================================

import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js";
import { getDatabase } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-database.js";

const firebaseConfig = {
  apiKey:            "PEGA_TU_apiKey_AQUI",
  authDomain:        "lore-basedatos.firebaseapp.com",
  databaseURL:       "https://lore-basedatos-default-rtdb.firebaseio.com",
  projectId:         "lore-basedatos",
  storageBucket:     "lore-basedatos.appspot.com",
  messagingSenderId: "PEGA_TU_messagingSenderId_AQUI",
  appId:             "PEGA_TU_appId_AQUI"
};

const app = initializeApp(firebaseConfig);
export const db  = getDatabase(app);
