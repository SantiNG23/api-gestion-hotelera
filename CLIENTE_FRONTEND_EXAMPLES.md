# Ejemplos de Frontend - Módulo de Clientes

Este documento contiene ejemplos de cómo el frontend debe interactuar con la API de clientes, considerando las validaciones y estructura esperada por el backend.

---

## 📋 Tabla de Contenidos

1. [Validaciones del Backend](#validaciones-del-backend)
2. [Estructura de Datos](#estructura-de-datos)
3. [Endpoints Disponibles](#endpoints-disponibles)
4. [Ejemplos de Requests](#ejemplos-de-requests)
5. [Ejemplos de Responses](#ejemplos-de-responses)
6. [Manejo de Errores](#manejo-de-errores)
7. [Ejemplo Completo en JavaScript/Vue](#ejemplo-completo-en-javascriptvue)

---

## 🔐 Validaciones del Backend

### Reglas de Validación para Crear/Actualizar Cliente

```javascript
// CREACIÓN DE CLIENTE (POST)
{
  name: {
    required: true,
    type: 'string',
    maxLength: 255,
    errorMessage: 'El nombre es obligatorio'
  },
  dni: {
    required: true,
    type: 'string',
    maxLength: 20,
    unique: true, // Único por tenant (no puede repetirse)
    errorMessage: 'El DNI es obligatorio',
    uniqueError: 'Ya existe un cliente con este DNI'
  },
  age: {
    required: false,
    type: 'integer',
    min: 0,
    max: 150,
    errorMessage: 'La edad debe ser un número entero entre 0 y 150'
  },
  city: {
    required: false,
    type: 'string',
    maxLength: 255,
    errorMessage: 'La ciudad no debe exceder 255 caracteres'
  },
  phone: {
    required: false,
    type: 'string',
    maxLength: 50,
    errorMessage: 'El teléfono no debe exceder 50 caracteres'
  },
  email: {
    required: false,
    type: 'email',
    maxLength: 255,
    errorMessage: 'El email debe tener un formato válido'
  }
}

// ACTUALIZACIÓN DE CLIENTE (PUT/PATCH)
// - Todos los campos excepto dni y name son opcionales (sometimes)
// - El dni, si se envía, debe ser único (pero permite el mismo DNI del cliente actual)
```

---

## 📦 Estructura de Datos

### Cliente (Respuesta del Backend)

```json
{
    "id": 1,
    "name": "Juan Pérez García",
    "dni": "12345678A",
    "age": 35,
    "city": "Madrid",
    "phone": "+34 91 234 5678",
    "email": "juan.perez@example.com",
    "created_at": "2024-12-01T10:30:00Z",
    "updated_at": "2024-12-10T15:45:00Z",
    "reservations_count": 3
}
```

### Cliente con Reservaciones (GET /v1/clients/{id})

```json
{
    "id": 1,
    "name": "Juan Pérez García",
    "dni": "12345678A",
    "age": 35,
    "city": "Madrid",
    "phone": "+34 91 234 5678",
    "email": "juan.perez@example.com",
    "reservations": [
        {
            "id": 1,
            "check_in_date": "2024-12-15",
            "check_out_date": "2024-12-20",
            "total_price": 450.0,
            "status": "confirmed",
            "cabin": {
                "id": 1,
                "name": "Cabaña Montaña",
                "capacity": 4
            }
        }
    ],
    "reservations_count": 3
}
```

---

## 🌐 Endpoints Disponibles

| Método       | Endpoint                       | Descripción                              |
| ------------ | ------------------------------ | ---------------------------------------- |
| `GET`        | `/v1/clients`                  | Listar clientes con paginación y filtros |
| `POST`       | `/v1/clients`                  | Crear nuevo cliente                      |
| `GET`        | `/v1/clients/{id}`             | Obtener cliente con sus reservaciones    |
| `PUT\|PATCH` | `/v1/clients/{id}`             | Actualizar cliente                       |
| `DELETE`     | `/v1/clients/{id}`             | Eliminar cliente                         |
| `GET`        | `/v1/clients/search/dni/{dni}` | Buscar cliente por DNI                   |

---

## 📤 Ejemplos de Requests

### 1. Crear Cliente

```javascript
// POST /v1/clients
const createClientData = {
    name: "Ana García López",
    dni: "87654321B",
    age: 28,
    city: "Barcelona",
    phone: "+34 93 123 4567",
    email: "ana.garcia@example.com",
};

// Con fetch
fetch("https://api.example.com/v1/clients", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify(createClientData),
})
    .then((response) => response.json())
    .then((data) => console.log(data));

// Con axios
axios.post("/v1/clients", createClientData, {
    headers: {
        Authorization: `Bearer ${token}`,
    },
});
```

### 2. Actualizar Cliente

```javascript
// PUT /v1/clients/{id}
// Nota: Solo actualiza los campos que se envíen (except dni duplicated)
const updateClientData = {
    name: "Ana García López Actualizado",
    age: 29,
    phone: "+34 93 999 9999",
    // El email, city y dni no se tocan si no se envían
};

fetch("https://api.example.com/v1/clients/1", {
    method: "PUT",
    headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify(updateClientData),
})
    .then((response) => response.json())
    .then((data) => console.log(data));
```

### 3. Listar Clientes con Filtros

```javascript
// GET /v1/clients?page=1&per_page=15&filter[name]=Juan&sort=name

// Parámetros disponibles:
const params = {
    page: 1,
    per_page: 15,
    filter: {
        name: "Juan", // Búsqueda parcial (like)
        dni: "12345678", // Búsqueda parcial (like)
        city: "Madrid", // Búsqueda parcial (like)
        global: "juan", // Busca en: name, dni, city, phone, email
    },
    sort: "name", // O '-name' para orden descendente
};

// Con URLSearchParams
const searchParams = new URLSearchParams();
searchParams.append("page", params.page);
searchParams.append("per_page", params.per_page);
searchParams.append("filter[name]", params.filter.name);
searchParams.append("sort", params.sort);

fetch(`https://api.example.com/v1/clients?${searchParams.toString()}`, {
    method: "GET",
    headers: {
        Authorization: `Bearer ${token}`,
    },
})
    .then((response) => response.json())
    .then((data) => console.log(data));

// Con axios
axios.get("/v1/clients", {
    params: {
        page: 1,
        per_page: 15,
        "filter[name]": "Juan",
        sort: "name",
    },
});
```

### 4. Buscar Cliente por DNI

```javascript
// GET /v1/clients/search/dni/12345678A
// Retorna el cliente con sus reservaciones

fetch("https://api.example.com/v1/clients/search/dni/12345678A", {
    method: "GET",
    headers: {
        Authorization: `Bearer ${token}`,
    },
})
    .then((response) => response.json())
    .then((data) => {
        if (response.ok) {
            console.log("Cliente encontrado:", data.data);
        }
    })
    .catch((error) => console.error("Cliente no encontrado", error));
```

### 5. Obtener Cliente con Reservaciones

```javascript
// GET /v1/clients/1
// Retorna cliente con array de reservaciones incluidas

fetch("https://api.example.com/v1/clients/1", {
    method: "GET",
    headers: {
        Authorization: `Bearer ${token}`,
    },
})
    .then((response) => response.json())
    .then((data) => {
        console.log("Datos del cliente:", data.data);
        console.log("Sus reservaciones:", data.data.reservations);
    });
```

### 6. Eliminar Cliente

```javascript
// DELETE /v1/clients/1

fetch("https://api.example.com/v1/clients/1", {
    method: "DELETE",
    headers: {
        Authorization: `Bearer ${token}`,
    },
})
    .then((response) => response.json())
    .then((data) => console.log("Cliente eliminado exitosamente"));
```

---

## 📥 Ejemplos de Responses

### Respuesta Exitosa - Listar Clientes

```json
{
    "success": true,
    "message": null,
    "data": [
        {
            "id": 1,
            "name": "Juan Pérez García",
            "dni": "12345678A",
            "age": 35,
            "city": "Madrid",
            "phone": "+34 91 234 5678",
            "email": "juan.perez@example.com"
        },
        {
            "id": 2,
            "name": "Ana García López",
            "dni": "87654321B",
            "age": 28,
            "city": "Barcelona",
            "phone": "+34 93 123 4567",
            "email": "ana.garcia@example.com"
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 15,
        "total": 42,
        "last_page": 3,
        "from": 1,
        "to": 15
    }
}
```

### Respuesta Exitosa - Crear Cliente

```json
{
    "success": true,
    "message": "Cliente creado exitosamente",
    "data": {
        "id": 3,
        "name": "Carlos López Martín",
        "dni": "11223344C",
        "age": 42,
        "city": "Valencia",
        "phone": "+34 96 111 2222",
        "email": "carlos.lopez@example.com"
    }
}
```

### Respuesta Exitosa - Obtener Cliente

```json
{
    "success": true,
    "message": null,
    "data": {
        "id": 1,
        "name": "Juan Pérez García",
        "dni": "12345678A",
        "age": 35,
        "city": "Madrid",
        "phone": "+34 91 234 5678",
        "email": "juan.perez@example.com",
        "reservations": [
            {
                "id": 1,
                "check_in_date": "2024-12-15",
                "check_out_date": "2024-12-20",
                "total_price": 450.0,
                "status": "confirmed",
                "cabin": {
                    "id": 1,
                    "name": "Cabaña Montaña",
                    "capacity": 4
                }
            }
        ],
        "reservations_count": 3
    }
}
```

---

## ⚠️ Manejo de Errores

### Error de Validación

```json
{
    "success": false,
    "message": "Error de validación",
    "errors": {
        "name": ["El nombre es obligatorio"],
        "email": ["El email debe tener un formato válido"]
    }
}
// Status Code: 422
```

### Error de DNI Duplicado

```json
{
    "success": false,
    "message": "Error de validación",
    "errors": {
        "dni": ["Ya existe un cliente con este DNI"]
    }
}
// Status Code: 422
```

### Error de Cliente no Encontrado

```json
{
    "success": false,
    "message": "Cliente no encontrado",
    "data": null
}
// Status Code: 404
```

### Error de No Autorizado

```json
{
    "success": false,
    "message": "No autorizado",
    "data": null
}
// Status Code: 401
```

---

## 💻 Ejemplo Completo en JavaScript/Vue

### Composable de Clientes (Vue 3)

```javascript
// composables/useClients.js
import { ref, reactive } from "vue";
import axios from "axios";

const API_BASE = "https://api.example.com/v1";

export function useClients() {
    const clients = ref([]);
    const loading = ref(false);
    const errors = ref({});
    const pagination = ref(null);

    const filters = reactive({
        page: 1,
        per_page: 15,
        global: "",
        name: "",
        dni: "",
        city: "",
    });

    // Listar clientes
    const fetchClients = async () => {
        loading.value = true;
        errors.value = {};

        try {
            const params = new URLSearchParams();
            params.append("page", filters.page);
            params.append("per_page", filters.per_page);

            if (filters.global) params.append("filter[global]", filters.global);
            if (filters.name) params.append("filter[name]", filters.name);
            if (filters.dni) params.append("filter[dni]", filters.dni);
            if (filters.city) params.append("filter[city]", filters.city);

            const response = await axios.get(`${API_BASE}/clients?${params}`);

            clients.value = response.data.data;
            pagination.value = response.data.pagination;
        } catch (error) {
            handleError(error);
        } finally {
            loading.value = false;
        }
    };

    // Crear cliente
    const createClient = async (formData) => {
        loading.value = true;
        errors.value = {};

        try {
            const response = await axios.post(`${API_BASE}/clients`, formData);

            clients.value.unshift(response.data.data);
            return response.data.data;
        } catch (error) {
            handleError(error);
            throw error;
        } finally {
            loading.value = false;
        }
    };

    // Actualizar cliente
    const updateClient = async (clientId, formData) => {
        loading.value = true;
        errors.value = {};

        try {
            const response = await axios.put(
                `${API_BASE}/clients/${clientId}`,
                formData
            );

            // Actualizar en la lista
            const index = clients.value.findIndex((c) => c.id === clientId);
            if (index !== -1) {
                clients.value[index] = response.data.data;
            }

            return response.data.data;
        } catch (error) {
            handleError(error);
            throw error;
        } finally {
            loading.value = false;
        }
    };

    // Obtener cliente
    const getClient = async (clientId) => {
        loading.value = true;
        errors.value = {};

        try {
            const response = await axios.get(`${API_BASE}/clients/${clientId}`);
            return response.data.data;
        } catch (error) {
            handleError(error);
            throw error;
        } finally {
            loading.value = false;
        }
    };

    // Buscar por DNI
    const searchByDni = async (dni) => {
        loading.value = true;
        errors.value = {};

        try {
            const response = await axios.get(
                `${API_BASE}/clients/search/dni/${dni}`
            );
            return response.data.data;
        } catch (error) {
            if (error.response?.status === 404) {
                errors.value.general = "Cliente no encontrado";
                return null;
            }
            handleError(error);
            throw error;
        } finally {
            loading.value = false;
        }
    };

    // Eliminar cliente
    const deleteClient = async (clientId) => {
        loading.value = true;
        errors.value = {};

        try {
            await axios.delete(`${API_BASE}/clients/${clientId}`);

            clients.value = clients.value.filter((c) => c.id !== clientId);
            return true;
        } catch (error) {
            handleError(error);
            throw error;
        } finally {
            loading.value = false;
        }
    };

    // Manejo de errores
    const handleError = (error) => {
        if (error.response?.status === 422 && error.response.data.errors) {
            errors.value = error.response.data.errors;
        } else if (error.response?.status === 401) {
            errors.value.general = "No autenticado. Por favor inicia sesión.";
        } else if (error.response?.status === 403) {
            errors.value.general =
                "No tienes permiso para realizar esta acción.";
        } else if (error.response?.status === 404) {
            errors.value.general = "Cliente no encontrado.";
        } else {
            errors.value.general = "Error al procesar la solicitud.";
        }
    };

    return {
        // State
        clients,
        loading,
        errors,
        pagination,
        filters,

        // Methods
        fetchClients,
        createClient,
        updateClient,
        getClient,
        searchByDni,
        deleteClient,
    };
}
```

### Componente de Formulario (Vue 3)

```vue
<template>
    <div class="client-form">
        <form @submit.prevent="submitForm">
            <!-- Nombre -->
            <div class="form-group">
                <label for="name">Nombre *</label>
                <input
                    id="name"
                    v-model="formData.name"
                    type="text"
                    placeholder="Ej: Juan Pérez García"
                    maxlength="255"
                />
                <span v-if="errors.name" class="error">
                    {{ errors.name[0] }}
                </span>
            </div>

            <!-- DNI -->
            <div class="form-group">
                <label for="dni">DNI *</label>
                <input
                    id="dni"
                    v-model="formData.dni"
                    type="text"
                    placeholder="Ej: 12345678A"
                    maxlength="20"
                    @blur="validateDniUnique"
                />
                <span v-if="errors.dni" class="error">
                    {{ errors.dni[0] }}
                </span>
            </div>

            <!-- Edad -->
            <div class="form-group">
                <label for="age">Edad</label>
                <input
                    id="age"
                    v-model.number="formData.age"
                    type="number"
                    min="0"
                    max="150"
                    placeholder="Ej: 35"
                />
                <span v-if="errors.age" class="error">
                    {{ errors.age[0] }}
                </span>
            </div>

            <!-- Ciudad -->
            <div class="form-group">
                <label for="city">Ciudad</label>
                <input
                    id="city"
                    v-model="formData.city"
                    type="text"
                    placeholder="Ej: Madrid"
                    maxlength="255"
                />
            </div>

            <!-- Teléfono -->
            <div class="form-group">
                <label for="phone">Teléfono</label>
                <input
                    id="phone"
                    v-model="formData.phone"
                    type="tel"
                    placeholder="Ej: +34 91 234 5678"
                    maxlength="50"
                />
            </div>

            <!-- Email -->
            <div class="form-group">
                <label for="email">Email</label>
                <input
                    id="email"
                    v-model="formData.email"
                    type="email"
                    placeholder="Ej: juan@example.com"
                    maxlength="255"
                />
                <span v-if="errors.email" class="error">
                    {{ errors.email[0] }}
                </span>
            </div>

            <!-- Error general -->
            <div v-if="errors.general" class="error-general">
                {{ errors.general }}
            </div>

            <!-- Botón -->
            <button type="submit" :disabled="loading">
                {{
                    loading ? "Guardando..." : clientId ? "Actualizar" : "Crear"
                }}
            </button>
        </form>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import { useClients } from "@/composables/useClients";

const props = defineProps({
    clientId: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(["success"]);

const { createClient, updateClient, getClient, loading, errors } = useClients();

const formData = reactive({
    name: "",
    dni: "",
    age: null,
    city: "",
    phone: "",
    email: "",
});

onMounted(async () => {
    if (props.clientId) {
        const client = await getClient(props.clientId);
        if (client) {
            Object.assign(formData, client);
        }
    }
});

const validateDniUnique = async () => {
    // La validación de unicidad se hace en el servidor
    // En el frontend solo validamos formato
    if (formData.dni && formData.dni.length < 5) {
        errors.value.dni = ["El DNI debe tener al menos 5 caracteres"];
    }
};

const submitForm = async () => {
    try {
        // Filtrar campos vacíos para PUT
        const dataToSend = props.clientId
            ? Object.fromEntries(
                  Object.entries(formData).filter(
                      ([_, value]) => value !== "" && value !== null
                  )
              )
            : formData;

        if (props.clientId) {
            await updateClient(props.clientId, dataToSend);
        } else {
            await createClient(dataToSend);
        }

        emit("success");
    } catch (error) {
        console.error("Error al guardar cliente:", error);
    }
};
</script>

<style scoped>
.client-form {
    max-width: 500px;
    margin: 0 auto;
}

.form-group {
    margin-bottom: 1.5rem;
    display: flex;
    flex-direction: column;
}

label {
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

input {
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

input:focus {
    outline: none;
    border-color: #4caf50;
    box-shadow: 0 0 5px rgba(76, 175, 80, 0.3);
}

.error {
    color: #d32f2f;
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.error-general {
    background-color: #ffebee;
    border-left: 4px solid #d32f2f;
    color: #d32f2f;
    padding: 1rem;
    margin-bottom: 1rem;
    border-radius: 4px;
}

button {
    padding: 0.75rem 1.5rem;
    background-color: #4caf50;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
}

button:hover:not(:disabled) {
    background-color: #45a049;
}

button:disabled {
    background-color: #cccccc;
    cursor: not-allowed;
}
</style>
```

---

## ✅ Checklist para Validación en Frontend

Antes de enviar datos al servidor, verifica:

-   [ ] El **nombre** tiene al menos 1 carácter y no excede 255
-   [ ] El **DNI** tiene al menos 5 caracteres y no excede 20
-   [ ] La **edad** (si se proporciona) es un número entre 0 y 150
-   [ ] La **ciudad** (si se proporciona) no excede 255 caracteres
-   [ ] El **teléfono** (si se proporciona) no excede 50 caracteres
-   [ ] El **email** (si se proporciona) tiene formato válido
-   [ ] Los campos obligatorios (nombre, DNI) están rellenos en creación
-   [ ] En actualización, solo se envían campos que cambiaron
-   [ ] El usuario está autenticado (token en headers)
-   [ ] Se manejan errores de validación del servidor (422)
-   [ ] Se notifica al usuario si el DNI ya existe

---

## 🔗 URLs Base Según Ambiente

```javascript
// Desarrollo
const API_BASE = "http://localhost:8000/api/v1";

// Staging
const API_BASE = "https://staging-api.example.com/api/v1";

// Producción
const API_BASE = "https://api.example.com/api/v1";
```

---

## 📝 Notas Importantes

1. **Autenticación**: Todos los endpoints de clientes requieren token de bearer en el header `Authorization`
2. **Paginación**: Por defecto se devuelven 15 clientes por página
3. **Filtros**: Son búsquedas parciales (LIKE), no exactas
4. **DNI Único**: La validación de DNI único es por tenant, no global
5. **Soft Delete**: El cliente se elimina lógicamente (no se borra de la BD)
6. **Relaciones**: Al obtener un cliente, puedes ver todas sus reservaciones
7. **Rate Limiting**: Considera implementar debounce en búsquedas
8. **Validación Frontal**: Realiza validaciones básicas pero confía en el servidor para las definitivas
