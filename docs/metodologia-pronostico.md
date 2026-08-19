# Metodología del pronóstico sísmico a 6 meses

**Plugin:** Sismos Nariño · `includes/analysis/class-sis-forecast.php`
**Versión:** 1.0.0 · **Última revisión:** 2026-08-19

Este documento describe, con el detalle necesario para auditarlo o reproducirlo, cómo se calcula el pronóstico que publica el plugin. Todo el método es determinista: con el mismo catálogo de entrada el resultado es siempre idéntico, y el catálogo se publica íntegro en la API abierta.

---

## 0. Qué se pronostica y qué no

**Sí:** la *tasa esperada* de sismos por encima de la magnitud de completitud durante los próximos seis meses, su banda de incertidumbre, la probabilidad de superar distintos umbrales de magnitud, el periodo de retorno asociado, la magnitud máxima esperable y la energía esperada.

**No:** el lugar, la fecha o la hora de un sismo concreto. Ningún método científico lo permite hoy. Estas cifras sirven para dimensionar la amenaza y planear; los avisos de emergencia los emiten el Servicio Geológico Colombiano (SGC) y la UNGRD.

---

## 1. Preparación del catálogo

1. Se descarga el catálogo del **USGS FDSN Event Web Service** en GeoJSON, recortado al ámbito espacial (recuadro o círculo) y a la ventana de años configurada.
2. Se descartan los eventos sin magnitud y los que no son de tipo `earthquake` (explosiones, derrumbes, ruido).
3. Cada evento se normaliza a un esquema plano y se le asigna el municipio de Nariño más cercano por distancia ortodrómica (haversine) al centroide municipal.
4. Los eventos del feed de sismicidad reciente se fusionan por `id`, de modo que la estadística incluya lo ocurrido después de la última sincronización pesada.

---

## 2. Completitud del catálogo (Mc)

La red global no registra todos los sismos pequeños. Por debajo de cierta magnitud el catálogo está incompleto y cualquier ajuste hecho ahí estaría sesgado.

Se estima **Mc por máxima curvatura** (Wiemer & Wyss, 2000): la magnitud con mayor frecuencia no acumulada, más la corrección estándar de **+0,2** que compensa el sesgo conocido del método.

Si con ese Mc quedan menos de 30 eventos, se relaja de a 0,1 hasta alcanzar una muestra utilizable; el valor efectivo se publica siempre junto al resultado.

---

## 3. Ley de Gutenberg-Richter (fondo climatológico)

Sobre los eventos con M ≥ Mc:

**Valor b** — estimador de máxima verosimilitud de Aki (1965) con corrección de discretización (Utsu, 1966):

```
b = log10(e) / ( M̄ − (Mc − ΔM/2) )        ΔM = 0,1
```

**Incertidumbre de b** — Shi & Bolt (1982):

```
σ_b = 2,30 · b² · sqrt( Σ(Mi − M̄)² / (n(n−1)) )
```

**Valor a**, normalizado a tasa anual sobre los años cubiertos por el catálogo:

```
a = log10(N / años) + b·Mc
```

**Tasa anual esperada** de eventos con M ≥ m:

```
λ(m) = 10^(a − b·m)
```

El fondo climatológico mensual del modelo es `λ_base = (N/años)/12`, es decir, la tasa observada sobre Mc repartida por mes.

---

## 4. Estado reciente (Holt amortiguado)

La serie de conteos mensuales de eventos con M ≥ Mc se rellena con ceros —un mes en calma es una observación— hasta el **último mes completo que cubre el catálogo**. Sobre ella se aplica un suavizado exponencial con tendencia amortiguada:

```
nivel_t     = α·y_t + (1−α)·(nivel_{t−1} + φ·tend_{t−1})
tendencia_t = β·(nivel_t − nivel_{t−1}) + (1−β)·φ·tend_{t−1}
ŷ_{t+h}     = nivel_t + (φ + φ² + … + φ^h)·tend_t
```

La proyección se acota por abajo al 10 % del fondo: una racha en calma baja la expectativa, pero una tasa exactamente nula no es creíble en una zona activa.

---

## 5. Mezcla y reversión a la media

Para cada mes futuro *h* (1 … 6):

```
w_h    = w₀ · φ_w^(h−1)                       (peso del estado reciente)
λ_h    = w_h·λ_reciente,h + (1 − w_h)·λ_base + λ_réplicas,h
```

Con los valores por defecto (w₀ = 0,70 · φ_w = 0,75) el estado reciente pesa un 70 % en el primer mes y menos del 17 % en el sexto: **el pronóstico cercano sigue a los datos recientes y el lejano revierte a la climatología**, que es lo honesto cuando la memoria del proceso se agota.

---

## 6. Réplicas (Omori-Utsu + Reasenberg & Jones)

Si en los últimos 365 días ocurrió un sismo detonante con M ≥ Mc + 1,0, se añade la tasa de réplicas esperada:

```
λ(t) = K / (t + c)^p          [réplicas por día]
K    = 10^( a_RJ + b·(Mm − Mc) )
```

Parámetros genéricos: `a_RJ = −1,67`, `p = 1,08`, `c = 0,05 días` (Reasenberg & Jones, 1989/1994). Son genéricos, no calibrados para esta zona: el plugin lo declara explícitamente.

La contribución de cada mes futuro se obtiene integrando analíticamente:

```
∫ K/(t+c)^p dt = K·[(t+c)^(1−p)] / (1−p)          (p ≠ 1)
```

Esta componente es la que hace **subir el pronóstico justo después de un sismo importante y bajar solo** a medida que la secuencia se apaga.

---

## 7. Banda de incertidumbre

Intervalo de predicción de Poisson al 90 %, usando su relación exacta con la chi-cuadrado:

```
límite inferior = ½·χ²(α/2 ; 2λ)
límite superior = ½·χ²(1−α/2 ; 2λ+2)
```

Los cuantiles de χ² se calculan por la aproximación de Wilson-Hilferty sobre el cuantil normal (Acklam). La banda recoge la aleatoriedad del proceso, **no** la incertidumbre de los parámetros ajustados: es, por tanto, un límite inferior de la incertidumbre total.

---

## 8. Probabilidad por umbral de magnitud

El número esperado de eventos por encima de un umbral *m* se obtiene repartiendo la tasa total con una **Gutenberg-Richter truncada** en la mayor ruptura creíble del dominio (M_max = 8,8; referencia: terremoto de Esmeraldas–Tumaco de 1906):

```
P(M ≥ m | M ≥ Mc) = [10^(−b(m−Mc)) − 10^(−b(M_max−Mc))] / [1 − 10^(−b(M_max−Mc))]
N_m               = N_total · P(M ≥ m | M ≥ Mc)
P(al menos uno)   = 1 − e^(−N_m)
Periodo de retorno = 1 / (tasa anual equivalente)
```

El truncamiento evita asignar probabilidad no nula a magnitudes físicamente imposibles, que es lo que ocurre con la ley sin truncar.

**Magnitud máxima esperada**, a partir de la distribución del máximo `P(Mmax < m) = exp(−N·P(M ≥ m))`: se publican la moda (el tamaño que cabe esperar «una vez» en la ventana), la mediana y el percentil 90.

**Energía esperada**: se integra la GR truncada por intervalos de 0,1 aplicando la relación de Hanks & Kanamori `log10(E) = 1,5·M + 4,8`, y se expresa en toneladas equivalentes de TNT.

---

## 9. Actualización: por qué el pronóstico cambia

La caché del pronóstico se indexa por una **firma del catálogo** = `md5(nº de eventos | id del último sismo | hora del último sismo)`, truncada a 12 caracteres.

- Llega un sismo nuevo (por cron o por el feed) → cambia la firma → falla la caché → **se recalcula**.
- Cambian los parámetros del modelo en el panel → se limpia el grupo de caché `pronostico` → **se recalcula**.
- Se actualiza el plugin → se limpia el grupo → **se recalcula**.

Cada resultado guarda una copia durable como «pronóstico anterior», de modo que el siguiente publique **cuánto y en qué sentido cambió** (`comparacion.texto`). Esa comparación es parte de la ficha pública: el pronóstico no solo se actualiza, también deja constancia de su propia evolución.

---

## 10. Parámetros configurables

| Parámetro | Por defecto | Rango | Qué controla |
|---|---|---|---|
| `ambito` | `regional` | catálogo de ámbitos | Dominio espacial del modelo |
| `horizonte` | 6 | 1–24 | Meses pronosticados |
| `confianza` | 0,90 | 0,50–0,99 | Nivel de la banda de Poisson |
| `alfa` (α) | 0,35 | 0,01–1 | Suavizado del nivel |
| `beta` (β) | 0,12 | 0–1 | Suavizado de la tendencia |
| `phi` (φ) | 0,85 | 0,1–1 | Amortiguamiento de la tendencia |
| `peso0` (w₀) | 0,70 | 0–1 | Peso inicial del estado reciente |
| `phi_peso` (φ_w) | 0,75 | 0,1–1 | Velocidad de reversión a la climatología |
| `meses_recientes` | 60 | 12–600 | Cola de la serie mensual usada |
| `umbrales` | 5,0 · 5,5 · 6,0 · 6,5 · 7,0 | — | Magnitudes publicadas (Mc se añade siempre) |

---

## 11. Limitaciones declaradas

1. **Poisson sin memoria.** El modelo trata la ocurrencia como un proceso de Poisson modulado; no incorpora ciclos sísmicos ni modelos de recurrencia característica de la interfaz de subducción. Para la interfaz mayor (M ≥ 8) la extrapolación de la GR es una referencia, no una evaluación de amenaza sismotectónica.
2. **Parámetros de réplicas genéricos.** `a_RJ`, `p` y `c` no están calibrados para el suroccidente colombiano.
3. **Catálogo global.** El USGS es completo en la región desde M≈4,5. La sismicidad menor —relevante para fallas corticales andinas— no está representada; el catálogo del SGC la cubriría mejor y el plugin admite añadirlo como fuente.
4. **Banda parcial.** No incluye la incertidumbre de estimación de b, a ni Mc.
5. **Sin declustering.** El catálogo no se descompone en principales y réplicas para el ajuste de la GR; se compensa modelando las réplicas aparte, pero el valor b puede quedar ligeramente sesgado tras grandes secuencias.

Estas limitaciones se publican junto al resultado, no en letra pequeña: comunicar la incertidumbre es parte del producto.

---

## Referencias

- Aki, K. (1965). *Maximum likelihood estimate of b in the formula log N = a − bM.* Bull. Earthq. Res. Inst.
- Utsu, T. (1966). *A statistical significance test of the difference in b-value between two earthquake groups.*
- Shi, Y. & Bolt, B. (1982). *The standard error of the magnitude-frequency b value.* BSSA.
- Wiemer, S. & Wyss, M. (2000). *Minimum magnitude of completeness in earthquake catalogs.* BSSA.
- Reasenberg, P. & Jones, L. (1989, 1994). *Earthquake hazard after a mainshock in California.* Science.
- Hanks, T. & Kanamori, H. (1979). *A moment magnitude scale.* JGR.
- Gardner, J. & Knopoff, L. (1974). *Is the sequence of earthquakes in Southern California, with aftershocks removed, Poissonian?* BSSA.
- U.S. Geological Survey — Earthquake Hazards Program. *FDSN Event Web Service Documentation.*
