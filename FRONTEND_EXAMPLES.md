# Ejemplos de Integración Frontend - API de Tarifas

## 1. Crear un Grupo de Precio

### Request

```javascript
// POST /api/v1/price-groups
const priceGroup = {
    name: "Temporada Alta",
    price_per_night: 250.5,
    priority: 20, // Opcional, default: 0
    is_default: false,
};

fetch("/api/v1/price-groups", {
    method: "POST",
    headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
    },
    body: JSON.stringify(priceGroup),
});
```

### Response (201 Created)

```json
{
    "success": true,
    "message": "Grupo de precio creado exitosamente",
    "data": {
        "id": 1,
        "name": "Temporada Alta",
        "price_per_night": 250.5,
        "priority": 20,
        "is_default": false,
        "created_at": "2025-12-11T20:30:00.000000Z",
        "updated_at": "2025-12-11T20:30:00.000000Z"
    }
}
```

---

## 2. Crear un Rango de Precio

### Request

```javascript
// POST /api/v1/price-ranges
const priceRange = {
    price_group_id: 1,
    start_date: "2025-01-01", // Formato Y-m-d
    end_date: "2025-01-31", // Formato Y-m-d
};

fetch("/api/v1/price-ranges", {
    method: "POST",
    headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
    },
    body: JSON.stringify(priceRange),
});
```

### Response (201 Created)

```json
{
    "success": true,
    "message": "Rango de precio creado exitosamente",
    "data": {
        "id": 5,
        "price_group_id": 1,
        "start_date": "2025-01-01",
        "end_date": "2025-01-31",
        "price_group": {
            "id": 1,
            "name": "Temporada Alta",
            "price_per_night": 250.5,
            "priority": 20,
            "is_default": false
        },
        "created_at": "2025-12-11T20:35:00.000000Z",
        "updated_at": "2025-12-11T20:35:00.000000Z"
    }
}
```

---

## 3. Obtener Tarifas Aplicables (NUEVO - MÁS IMPORTANTE)

### Request

```javascript
// GET /api/v1/price-ranges/applicable-rates?start_date=2025-01-01&end_date=2025-01-31
const startDate = "2025-01-01";
const endDate = "2025-01-31";

fetch(
    `/api/v1/price-ranges/applicable-rates?start_date=${startDate}&end_date=${endDate}`,
    {
        method: "GET",
        headers: {
            Authorization: `Bearer ${token}`,
            "Content-Type": "application/json",
        },
    }
)
    .then((res) => res.json())
    .then((data) => {
        // Aquí recibes los datos del precio ganador
        console.log(data.data.rates);
    });
```

### Response (200 OK)

```json
{
    "success": true,
    "message": null,
    "data": {
        "start_date": "2025-01-01",
        "end_date": "2025-01-31",
        "rates": {
            "2025-01-01": 100.0,
            "2025-01-02": 100.0,
            "2025-01-03": 100.0,
            "2025-01-04": 100.0,
            "2025-01-05": 100.0,
            "2025-01-06": 100.0,
            "2025-01-07": 100.0,
            "2025-01-08": 200.0,
            "2025-01-09": 200.0,
            "2025-01-10": 200.0,
            "2025-01-11": 200.0,
            "2025-01-12": 200.0,
            "2025-01-13": 200.0,
            "2025-01-14": 200.0,
            "2025-01-15": 150.0,
            "2025-01-16": 150.0,
            "2025-01-17": 150.0,
            "2025-01-18": 100.0,
            "2025-01-19": 100.0,
            "2025-01-20": 100.0,
            "2025-01-21": 100.0,
            "2025-01-22": 100.0,
            "2025-01-23": 100.0,
            "2025-01-24": 100.0,
            "2025-01-25": 100.0,
            "2025-01-26": 100.0,
            "2025-01-27": 100.0,
            "2025-01-28": 100.0,
            "2025-01-29": 100.0,
            "2025-01-30": 100.0,
            "2025-01-31": 100.0
        }
    }
}
```

---

## 4. Ejemplo Completo - Vue.js / React

### Vue.js

```javascript
// store/priceStore.js
export const state = {
  selectedMonth: '2025-01',
  applicableRates: {},
  priceGroups: [],
  priceRanges: []
};

export const mutations = {
  SET_APPLICABLE_RATES(state, rates) {
    state.applicableRates = rates;
  },
  SET_PRICE_GROUPS(state, groups) {
    state.priceGroups = groups;
  }
};

export const actions = {
  async fetchApplicableRates({ commit }, { startDate, endDate }) {
    try {
      const response = await fetch(
        `/api/v1/price-ranges/applicable-rates?start_date=${startDate}&end_date=${endDate}`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json'
          }
        }
      );

      const json = await response.json();

      if (json.success) {
        // ✅ Aquí recibes rates con las claves en formato YYYY-MM-DD
        commit('SET_APPLICABLE_RATES', json.data.rates);
        return json.data.rates;
      }
    } catch (error) {
      console.error('Error fetching rates:', error);
    }
  },

  async createPriceGroup({ commit }, groupData) {
    try {
      const response = await fetch('/api/v1/price-groups', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          name: groupData.name,
          price_per_night: groupData.price_per_night,
          priority: groupData.priority || 0,  // ✅ Nombre exacto: priority
          is_default: groupData.is_default || false
        })
      });

      const json = await response.json();

      if (json.success) {
        return json.data;  // Contiene: id, name, price_per_night, priority, is_default
      }
    } catch (error) {
      console.error('Error creating price group:', error);
    }
  },

  async createPriceRange({ commit }, rangeData) {
    try {
      const response = await fetch('/api/v1/price-ranges', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          price_group_id: rangeData.price_group_id,
          start_date: rangeData.start_date,  // ✅ Formato: YYYY-MM-DD
          end_date: rangeData.end_date        // ✅ Formato: YYYY-MM-DD
        })
      });

      const json = await response.json();

      if (json.success) {
        return json.data;  // Contiene: id, price_group_id, start_date, end_date, created_at
      }
    } catch (error) {
      console.error('Error creating price range:', error);
    }
  }
};

// Component.vue
<template>
  <div class="prices-calendar">
    <!-- Mostrar calendario con precios -->
    <div v-for="(price, date) in applicableRates" :key="date" class="day">
      <span class="date">{{ formatDate(date) }}</span>
      <span class="price">${{ price.toFixed(2) }}</span>
    </div>

    <!-- Crear nuevo grupo de precio -->
    <form @submit.prevent="handleCreateGroup">
      <input v-model="form.name" placeholder="Nombre del grupo" />
      <input v-model.number="form.price_per_night" type="number" placeholder="Precio por noche" />
      <input v-model.number="form.priority" type="number" placeholder="Prioridad (opcional)" />
      <button type="submit">Crear Grupo</button>
    </form>

    <!-- Crear nuevo rango -->
    <form @submit.prevent="handleCreateRange">
      <select v-model="form.price_group_id">
        <option value="">Seleccionar grupo</option>
        <option v-for="group in priceGroups" :key="group.id" :value="group.id">
          {{ group.name }} (Prioridad: {{ group.priority }})
        </option>
      </select>
      <input v-model="form.start_date" type="date" />
      <input v-model="form.end_date" type="date" />
      <button type="submit">Crear Rango</button>
    </form>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';

export default {
  computed: {
    ...mapState('price', ['applicableRates', 'priceGroups'])
  },
  data() {
    return {
      form: {
        name: '',
        price_per_night: 0,
        priority: 0,
        price_group_id: null,
        start_date: '',
        end_date: ''
      }
    };
  },
  methods: {
    ...mapActions('price', ['fetchApplicableRates', 'createPriceGroup', 'createPriceRange']),

    async handleCreateGroup() {
      await this.createPriceGroup(this.form);
      this.form.name = '';
      this.form.price_per_night = 0;
      this.form.priority = 0;
    },

    async handleCreateRange() {
      await this.createPriceRange(this.form);
      // Recargar tarifas aplicables
      const today = new Date();
      const startDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-01`;
      const endDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-31`;
      await this.fetchApplicableRates({ startDate, endDate });
    },

    formatDate(dateString) {
      const date = new Date(dateString + 'T00:00:00');
      return date.toLocaleDateString('es-ES', { weekday: 'short', month: 'short', day: 'numeric' });
    }
  },
  mounted() {
    // Cargar tarifas al montar el componente
    const today = new Date();
    const startDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-01`;
    const endDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-31`;
    this.fetchApplicableRates({ startDate, endDate });
  }
};
</script>

<style scoped>
.prices-calendar {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 10px;
  margin-bottom: 20px;
}

.day {
  border: 1px solid #ddd;
  padding: 10px;
  text-align: center;
  border-radius: 4px;
}

.price {
  display: block;
  font-weight: bold;
  color: #2c3e50;
  font-size: 18px;
}

form {
  display: flex;
  gap: 10px;
  margin-top: 20px;
  flex-wrap: wrap;
}

input, select {
  padding: 8px;
  border: 1px solid #ccc;
  border-radius: 4px;
}

button {
  padding: 8px 16px;
  background-color: #42b983;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

button:hover {
  background-color: #369970;
}
</style>
```

### React

```javascript
// hooks/usePriceRates.js
import { useState, useCallback } from "react";

export const usePriceRates = () => {
    const [applicableRates, setApplicableRates] = useState({});
    const [priceGroups, setPriceGroups] = useState([]);
    const [loading, setLoading] = useState(false);

    const fetchApplicableRates = useCallback(async (startDate, endDate) => {
        setLoading(true);
        try {
            const response = await fetch(
                `/api/v1/price-ranges/applicable-rates?start_date=${startDate}&end_date=${endDate}`,
                {
                    headers: {
                        Authorization: `Bearer ${localStorage.getItem(
                            "token"
                        )}`,
                        "Content-Type": "application/json",
                    },
                }
            );

            const json = await response.json();

            if (json.success) {
                // ✅ rates es un objeto con claves en formato YYYY-MM-DD
                setApplicableRates(json.data.rates);
                return json.data.rates;
            }
        } catch (error) {
            console.error("Error fetching rates:", error);
        } finally {
            setLoading(false);
        }
    }, []);

    const createPriceGroup = useCallback(async (groupData) => {
        try {
            const response = await fetch("/api/v1/price-groups", {
                method: "POST",
                headers: {
                    Authorization: `Bearer ${localStorage.getItem("token")}`,
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    name: groupData.name,
                    price_per_night: groupData.price_per_night,
                    priority: groupData.priority || 0, // ✅ Campo: priority
                    is_default: groupData.is_default || false,
                }),
            });

            const json = await response.json();

            if (json.success) {
                return json.data; // { id, name, price_per_night, priority, is_default, ... }
            }
        } catch (error) {
            console.error("Error creating price group:", error);
        }
    }, []);

    const createPriceRange = useCallback(async (rangeData) => {
        try {
            const response = await fetch("/api/v1/price-ranges", {
                method: "POST",
                headers: {
                    Authorization: `Bearer ${localStorage.getItem("token")}`,
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    price_group_id: rangeData.price_group_id,
                    start_date: rangeData.start_date, // ✅ Formato: YYYY-MM-DD
                    end_date: rangeData.end_date, // ✅ Formato: YYYY-MM-DD
                }),
            });

            const json = await response.json();

            if (json.success) {
                return json.data; // { id, price_group_id, start_date, end_date, ... }
            }
        } catch (error) {
            console.error("Error creating price range:", error);
        }
    }, []);

    return {
        applicableRates,
        priceGroups,
        loading,
        fetchApplicableRates,
        createPriceGroup,
        createPriceRange,
    };
};

// PriceCalendar.jsx
import React, { useState, useEffect } from "react";
import { usePriceRates } from "./hooks/usePriceRates";

const PriceCalendar = () => {
    const {
        applicableRates,
        fetchApplicableRates,
        createPriceGroup,
        createPriceRange,
    } = usePriceRates();
    const [formGroup, setFormGroup] = useState({
        name: "",
        price_per_night: 0,
        priority: 0,
    });
    const [formRange, setFormRange] = useState({
        price_group_id: null,
        start_date: "",
        end_date: "",
    });

    useEffect(() => {
        // Cargar tarifas al montar el componente
        const today = new Date();
        const startDate = `${today.getFullYear()}-${String(
            today.getMonth() + 1
        ).padStart(2, "0")}-01`;
        const endDate = `${today.getFullYear()}-${String(
            today.getMonth() + 1
        ).padStart(2, "0")}-31`;
        fetchApplicableRates(startDate, endDate);
    }, []);

    const handleCreateGroup = async (e) => {
        e.preventDefault();
        await createPriceGroup(formGroup);
        setFormGroup({ name: "", price_per_night: 0, priority: 0 });
    };

    const handleCreateRange = async (e) => {
        e.preventDefault();
        await createPriceRange(formRange);
        setFormRange({ price_group_id: null, start_date: "", end_date: "" });
    };

    return (
        <div className="price-calendar">
            <div className="rates-grid">
                {Object.entries(applicableRates).map(([date, price]) => (
                    <div key={date} className="rate-day">
                        <span className="date">
                            {new Date(date + "T00:00:00").toLocaleDateString(
                                "es-ES"
                            )}
                        </span>
                        <span className="price">${price.toFixed(2)}</span>
                    </div>
                ))}
            </div>

            <form onSubmit={handleCreateGroup}>
                <h3>Crear Grupo de Precio</h3>
                <input
                    type="text"
                    placeholder="Nombre"
                    value={formGroup.name}
                    onChange={(e) =>
                        setFormGroup({ ...formGroup, name: e.target.value })
                    }
                />
                <input
                    type="number"
                    placeholder="Precio por noche"
                    value={formGroup.price_per_night}
                    onChange={(e) =>
                        setFormGroup({
                            ...formGroup,
                            price_per_night: parseFloat(e.target.value),
                        })
                    }
                />
                <input
                    type="number"
                    placeholder="Prioridad (opcional)"
                    value={formGroup.priority}
                    onChange={(e) =>
                        setFormGroup({
                            ...formGroup,
                            priority: parseInt(e.target.value),
                        })
                    }
                />
                <button type="submit">Crear</button>
            </form>

            <form onSubmit={handleCreateRange}>
                <h3>Crear Rango de Precio</h3>
                <input
                    type="date"
                    value={formRange.start_date}
                    onChange={(e) =>
                        setFormRange({
                            ...formRange,
                            start_date: e.target.value,
                        })
                    }
                />
                <input
                    type="date"
                    value={formRange.end_date}
                    onChange={(e) =>
                        setFormRange({ ...formRange, end_date: e.target.value })
                    }
                />
                <button type="submit">Crear</button>
            </form>
        </div>
    );
};

export default PriceCalendar;
```

---

## 5. Nomenclatura Exacta de Campos

### ✅ Campos EXACTOS que recibirás del Backend

**Grupo de Precio (PriceGroup):**

```javascript
{
  id: number,
  name: string,
  price_per_night: number,  // ✅ EXACTAMENTE: price_per_night
  priority: number,         // ✅ EXACTAMENTE: priority
  is_default: boolean,      // ✅ EXACTAMENTE: is_default
  created_at: string,       // ISO 8601 format
  updated_at: string
}
```

**Rango de Precio (PriceRange):**

```javascript
{
  id: number,
  price_group_id: number,   // ✅ EXACTAMENTE: price_group_id
  start_date: string,       // ✅ Formato: YYYY-MM-DD
  end_date: string,         // ✅ Formato: YYYY-MM-DD
  created_at: string,       // ISO 8601 format
  updated_at: string,
  price_group: {            // Relación cargada con `.with('priceGroup')`
    id: number,
    name: string,
    price_per_night: number,
    priority: number,
    is_default: boolean
  }
}
```

**Tarifas Aplicables (Response):**

```javascript
{
  success: boolean,
  message: string | null,
  data: {
    start_date: string,     // YYYY-MM-DD
    end_date: string,       // YYYY-MM-DD
    rates: {
      "2025-01-01": 100.00,  // ✅ Clave: YYYY-MM-DD, Valor: número
      "2025-01-02": 100.00,
      ...
    }
  }
}
```

---

## 6. Casos de Error

### Validación fallida

```json
{
    "success": false,
    "message": "Error de validación",
    "data": {
        "errors": {
            "start_date": ["La fecha de inicio debe ser hoy o posterior"],
            "end_date": [
                "La fecha de fin debe ser posterior a la fecha de inicio"
            ]
        }
    }
}
```

### No autenticado

```json
{
    "message": "Unauthenticated"
}
```

---

## 7. Resumen para el Frontend

| Campo             | Tipo    | Obligatorio | Ejemplo          |
| ----------------- | ------- | ----------- | ---------------- |
| `name` (grupo)    | string  | ✅          | "Temporada Alta" |
| `price_per_night` | number  | ✅          | 250.50           |
| `priority`        | number  | ❌          | 20               |
| `is_default`      | boolean | ❌          | false            |
| `price_group_id`  | number  | ✅          | 1                |
| `start_date`      | string  | ✅          | "2025-01-01"     |
| `end_date`        | string  | ✅          | "2025-01-31"     |
