<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

// Datos generales para las estadísticas de la cabecera
$totalRecetas =$db->query("SELECT COUNT(*) FROM recetas")->fetchColumn();
$totalIngredientes =$db->query("SELECT COUNT(*) FROM ingredientes")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RECETARIANDO - Catálogo y Buscador</title>
    <!-- Tailwind CSS para diseño moderno -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Script Oficial de Google Sign-In -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body class="bg-emerald-50/30 text-gray-800 font-sans min-h-screen flex flex-col">

    <!-- 1. BARRA DE NAVEGACIÓN SUPERIOR -->
    <nav class="bg-white border-b border-gray-200 sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <!-- Logo -->
                <div class="flex items-center gap-2 cursor-pointer" onclick="mostrarSeccion('catalogo')">
                    <span class="text-2xl">🍳</span>
                    <span class="font-extrabold text-xl tracking-tight text-emerald-600">RECETARIANDO</span>
                </div>

                <!-- Menú Central -->
                <div class="hidden md:flex space-x-8 font-medium text-gray-600">
                    <button onclick="mostrarSeccion('catalogo')" class="hover:text-emerald-600 transition">Catálogo</button>
                    <button onclick="mostrarSeccion('buscador')" class="hover:text-emerald-600 transition">Buscador</button>
                    <button onclick="mostrarSeccion('favoritos')" class="hover:text-emerald-600 transition flex items-center gap-1">
                        ❤️ Mis Favoritos
                    </button>
                </div>

                <!-- Inicio de Sesión con Google -->
                <div class="flex items-center gap-3">
                    <div id="g_id_onload"
                        data-client_id="628140116951-e59p05irrvk5p2fh3g4cbp7el6gjeb71.apps.googleusercontent.com"
                        data-callback="handleCredentialResponse"
                        data-auto_select="false">
                    </div>
                    
                    <div class="g_id_signin" data-type="standard" data-shape="pill" data-theme="outline" data-text="signin_with"></div>

                    <!-- Perfil del usuario una vez autenticado -->
                    <div id="userInfo" class="hidden flex items-center gap-2 bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-200 shadow-sm">
                        <img id="userAvatar" class="w-7 h-7 rounded-full border border-emerald-500 object-cover" src="" alt="Avatar">
                        <span id="userName" class="text-xs font-bold text-gray-800"></span>
                        <button onclick="cerrarSesion()" class="text-[10px] text-red-500 hover:underline ml-1 font-semibold">Salir</button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- 2. HERO / PORTADA -->
    <header class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white py-12 px-4 text-center shadow-inner">
        <h1 class="text-3xl md:text-5xl font-black mb-3 drop-shadow">Encuentra, explora y cocina como un chef</h1>
        <p class="text-emerald-100 max-w-xl mx-auto mb-6">Explora más de <?= $totalRecetas ?> recetas e información nutricional detallada.</p>
        
        <!-- Buscador rápido en Portada -->
        <div class="max-w-xl mx-auto flex gap-2">
            <input type="text" id="inputBuscarRapido" placeholder="Ej: Pollo, Sopa, Pasta..." 
                   class="w-full px-4 py-3 rounded-xl text-gray-800 shadow-md focus:outline-none focus:ring-2 focus:ring-emerald-300">
            <button onclick="buscarRapido()" class="bg-gray-900 text-white font-bold px-6 py-3 rounded-xl shadow-md hover:bg-gray-800 transition">
                Buscar
            </button>
        </div>
    </header>

    <!-- 3. CONTENEDOR PRINCIPAL -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-grow">

        <!-- SECCIÓN: CATÁLOGO DE RECETAS -->
        <section id="sec-catalogo">
            <h2 class="text-2xl font-bold mb-6 text-gray-900 border-b pb-2">✨ Recetas Destacadas</h2>
            <div id="grid-catalogo" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6"></div>
        </section>

        <!-- SECCIÓN: BUSCADOR CON FILTROS -->
        <section id="sec-buscador" class="hidden">
            <h2 class="text-2xl font-bold mb-4 text-gray-900">🔍 Buscador y Filtros Avanzados</h2>
            <div class="bg-white p-6 rounded-2xl shadow-sm border mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Nombre o Resumen</label>
                    <input type="text" id="filter-q" class="w-full border rounded-lg p-2 text-sm" placeholder="Buscar...">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Tiempo Máximo (minutos)</label>
                    <input type="number" id="filter-tiempo" class="w-full border rounded-lg p-2 text-sm" placeholder="Ej: 30">
                </div>
                <div class="flex items-end gap-4">
                    <label class="flex items-center gap-1 text-sm"><input type="checkbox" id="filter-vegano"> 🌱 Vegano</label>
                    <label class="flex items-center gap-1 text-sm"><input type="checkbox" id="filter-gluten"> 🚫🌾 Sin Gluten</label>
                </div>
                <div class="md:col-span-3">
                    <button onclick="ejecutarFiltros()" class="bg-emerald-600 text-white px-6 py-2 rounded-lg font-bold text-sm hover:bg-emerald-700">Aplicar Filtros</button>
                </div>
            </div>
            <div id="grid-busqueda" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6"></div>
        </section>

        <!-- SECCIÓN: MIS FAVORITOS -->
        <section id="sec-favoritos" class="hidden">
            <h2 class="text-2xl font-bold mb-6 text-gray-900 border-b pb-2">❤️ Mis Recetas Favoritas</h2>
            <div id="grid-favoritos" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6"></div>
        </section>

    </main>

    <!-- 4. VENTANA MODAL (DETALLE DE RECETA) -->
    <div id="modal-detalle" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4 hidden backdrop-blur-sm">
        <div class="bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl relative">
            
            <!-- Botón de Cerrar -->
            <button onclick="cerrarModal()" class="absolute top-4 right-4 bg-black/50 hover:bg-black/80 text-white w-8 h-8 rounded-full flex items-center justify-center z-10 transition">
                ✕
            </button>

            <!-- Cabecera e Imagen -->
            <div class="relative h-64 w-full">
                <img id="modal-img" class="w-full h-full object-cover rounded-t-3xl" src="" alt="Receta">
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent flex items-end p-6">
                    <h2 id="modal-titulo" class="text-2xl md:text-3xl font-black text-white drop-shadow"></h2>
                </div>
            </div>

            <!-- Meta Información -->
            <div class="p-6">
                <div class="flex flex-wrap gap-4 text-xs font-semibold text-gray-600 border-b pb-4 mb-6">
                    <span id="modal-tiempo" class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full">⏱️ 0 min</span>
                    <span id="modal-porciones" class="bg-teal-100 text-teal-700 px-3 py-1 rounded-full">👥 0 porciones</span>
                    <span id="modal-calificacion" class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full">⭐ 0.0</span>
                </div>

                <!-- Columnas de Ingredientes e Información Nutricional -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-bold text-gray-900 border-b pb-2 mb-3">🥦 Ingredientes</h3>
                        <ul id="modal-ingredientes" class="space-y-2 text-sm text-gray-700"></ul>
                    </div>

                    <div>
                        <h3 class="font-bold text-gray-900 border-b pb-2 mb-3">📊 Información Nutricional</h3>
                        <div id="modal-nutricion" class="space-y-3 text-xs"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. LÓGICA DE INTERACCIÓN (JAVASCRIPT) -->
    <script>
        const USER_ID = 1; // ID de usuario para pruebas locales

        document.addEventListener('DOMContentLoaded', () => {
            cargarRecetas();
            verificarSesion();
        });

        // --- GESTIÓN DE SESIÓN DE GOOGLE ---
        function parseJwt(token) {
            try {
                var base64Url = token.split('.')[1];
                var base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
                var jsonPayload = decodeURIComponent(window.atob(base64).split('').map(function(c) {
                    return '%' + ('0' + c.charCodeAt(0).toString(16)).slice(-2);
                }).join(''));
                return JSON.parse(jsonPayload);
            } catch (e) {
                return null;
            }
        }

        function handleCredentialResponse(response) {
            const usuario = parseJwt(response.credential);
            if (usuario) {
                document.querySelector('.g_id_signin').classList.add('hidden');
                document.getElementById('userInfo').classList.remove('hidden');
                document.getElementById('userName').innerText = usuario.given_name || usuario.name;
                document.getElementById('userAvatar').src = usuario.picture;

                localStorage.setItem('usuario_recetariando', JSON.stringify(usuario));
            }
        }

        function verificarSesion() {
            const usuarioGuardado = localStorage.getItem('usuario_recetariando');
            if (usuarioGuardado) {
                const usuario = JSON.parse(usuarioGuardado);
                document.querySelector('.g_id_signin').classList.add('hidden');
                document.getElementById('userInfo').classList.remove('hidden');
                document.getElementById('userName').innerText = usuario.given_name || usuario.name;
                document.getElementById('userAvatar').src = usuario.picture;
            }
        }

        function cerrarSesion() {
            localStorage.removeItem('usuario_recetariando');
            location.reload();
        }

        // --- NAVEGACIÓN Y VISTAS ---
        function mostrarSeccion(sec) {
            document.getElementById('sec-catalogo').classList.add('hidden');
            document.getElementById('sec-buscador').classList.add('hidden');
            document.getElementById('sec-favoritos').classList.add('hidden');

            if (sec === 'catalogo') {
                document.getElementById('sec-catalogo').classList.remove('hidden');
                cargarRecetas();
            } else if (sec === 'buscador') {
                document.getElementById('sec-buscador').classList.remove('hidden');
            } else if (sec === 'favoritos') {
                document.getElementById('sec-favoritos').classList.remove('hidden');
                cargarFavoritos();
            }
        }

        // --- PETICIONES API ---
        async function cargarRecetas() {
            const res = await fetch('api/recetas.php');
            const data = await res.json();
            renderizarTarjetas(data.recetas || [], 'grid-catalogo');
        }

        async function cargarFavoritos() {
            const res = await fetch('api/favoritos.php', {
                headers: { 'X-User-Id': USER_ID }
            });
            const data = await res.json();
            renderizarTarjetas(data || [], 'grid-favoritos');
        }

        async function alternarFavorito(recetaId) {
            const res = await fetch('api/favoritos.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-User-Id': USER_ID
                },
                body: JSON.stringify({ receta_id: recetaId })
            });
            const data = await res.json();
            alert(data.mensaje || data.error);
        }

        // --- RENDERIZADO DE TARJETAS Y MODAL ---
        function renderizarTarjetas(lista, contenedorId) {
            const container = document.getElementById(contenedorId);
            container.innerHTML = '';

            if (lista.length === 0) {
                container.innerHTML = `<p class="col-span-full text-center text-gray-500 py-8">No se encontraron recetas.</p>`;
                return;
            }

            lista.forEach(r => {
                const card = document.createElement('div');
                card.className = "bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-md transition duration-200 cursor-pointer flex flex-col justify-between";
                card.onclick = () => abrirDetalle(r.id);
                card.innerHTML = `
                    <div>
                        <img src="${r.imagen_url || 'https://via.placeholder.com/300x180?text=Sin+Imagen'}" class="w-full h-44 object-cover">
                        <div class="p-4">
                            <div class="flex justify-between items-center text-xs text-emerald-600 font-bold mb-1">
                                <span>⭐ ${r.calificacion || 'N/A'}</span>
                                <span>⏱️ ${(parseInt(r.tiempo_preparacion)||0) + (parseInt(r.tiempo_coccion)||0)} min</span>
                            </div>
                            <h3 class="font-bold text-gray-800 text-base mb-2 leading-snug">${r.titulo}</h3>
                        </div>
                    </div>
                    <div class="p-4 pt-0" onclick="event.stopPropagation()">
                        <button onclick="alternarFavorito(${r.id})" class="w-full py-2 bg-red-50 text-red-600 font-semibold text-xs rounded-xl hover:bg-red-100 transition flex items-center justify-center gap-1">
                            ❤️ Agregar a Favoritos
                        </button>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        async function abrirDetalle(id) {
            const res = await fetch(`api/detalle.php?id=${id}`);
            const data = await res.json();

            if (data.error) return alert(data.error);

            const r = data.receta;
            const ing = data.ingredientes;
            const nut = data.nutricion;

            document.getElementById('modal-img').src = r.imagen_url || 'https://via.placeholder.com/600x300';
            document.getElementById('modal-titulo').innerText = r.titulo;
            document.getElementById('modal-tiempo').innerText = `⏱️ ${(parseInt(r.tiempo_preparacion)||0) + (parseInt(r.tiempo_coccion)||0)} min`;
            document.getElementById('modal-porciones').innerText = `👥 ${r.porciones || 1} porciones`;
            document.getElementById('modal-calificacion').innerText = `⭐ ${r.calificacion || 'N/A'}`;

            // Ingredientes
            const ingContainer = document.getElementById('modal-ingredientes');
            ingContainer.innerHTML = ing.length === 0 ? '<li class="text-gray-400">Sin ingredientes.</li>' : '';
            ing.forEach(i => {
                ingContainer.innerHTML += `<li class="flex justify-between border-b border-gray-100 pb-1"><span>• ${i.nombre}</span> <b class="text-gray-500">${i.cantidad || ''} ${i.unidad || ''}</b></li>`;
            });

            // Nutrición
            const nutContainer = document.getElementById('modal-nutricion');
            if (nut) {
                nutContainer.innerHTML = `
                    <div>
                        <div class="flex justify-between mb-1"><span>Calorías</span><b>${nut.calorias || 0} kcal</b></div>
                        <div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-emerald-500 h-2 rounded-full" style="width: ${Math.min(100, (nut.calorias/800)*100)}%"></div></div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1"><span>Proteínas</span><b>${nut.proteinas || 0} g</b></div>
                        <div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-blue-500 h-2 rounded-full" style="width: ${Math.min(100, (nut.proteinas/50)*100)}%"></div></div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1"><span>Grasas</span><b>${nut.grasas || 0} g</b></div>
                        <div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-yellow-500 h-2 rounded-full" style="width: ${Math.min(100, (nut.grasas/70)*100)}%"></div></div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1"><span>Carbohidratos</span><b>${nut.carbohidratos || 0} g</b></div>
                        <div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-green-500 h-2 rounded-full" style="width: ${Math.min(100, (nut.carbohidratos/300)*100)}%"></div></div>
                    </div>
                `;
            } else {
                nutContainer.innerHTML = '<p class="text-gray-400">Sin información nutricional disponible.</p>';
            }

            document.getElementById('modal-detalle').classList.remove('hidden');
        }

        function cerrarModal() {
            document.getElementById('modal-detalle').classList.add('hidden');
        }

        // --- FILTROS Y BÚSQUEDA ---
        function buscarRapido() {
            const query = document.getElementById('inputBuscarRapido').value;
            mostrarSeccion('buscador');
            document.getElementById('filter-q').value = query;
            ejecutarFiltros();
        }

        async function ejecutarFiltros() {
            const q = document.getElementById('filter-q').value;
            const t = document.getElementById('filter-tiempo').value;
            const veg = document.getElementById('filter-vegano').checked ? 1 : '';
            const sg = document.getElementById('filter-gluten').checked ? 1 : '';

            const url = `api/buscar.php?q=${encodeURIComponent(q)}&tiempo_max=${t}&vegano=${veg}&sin_gluten=${sg}`;
            const res = await fetch(url);
            const data = await res.json();
            renderizarTarjetas(data.recetas || [], 'grid-busqueda');
        }
    </script>
</body>
</html>