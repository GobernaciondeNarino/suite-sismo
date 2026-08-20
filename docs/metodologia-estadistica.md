# Metodología estadística

**Plugin:** Sismos Nariño · `includes/analysis/` · **Revisión:** 2026-08-19

Todo lo que calcula el plugin es **retrospectivo**: describe la sismicidad registrada en la ventana consultada. No hay ninguna extrapolación hacia el futuro; el porqué está en [`marco-comunicacion-riesgo.md`](marco-comunicacion-riesgo.md).

---

## 1. Preparación del catálogo

1. Descarga del **USGS FDSN Event Web Service** en GeoJSON, recortada al ámbito espacial (recuadro o círculo) y a la ventana de años configurada.
2. Se descartan los eventos sin magnitud y los que no son de tipo `earthquake`.
3. Cada evento se normaliza a un esquema plano y se le asigna el municipio de Nariño más cercano por distancia ortodrómica (haversine) al centroide municipal.
4. Los eventos del feed de sismicidad reciente se fusionan por `id`, para que la estadística incluya lo ocurrido tras la última sincronización.

## 2. Magnitud de completitud (Mc)

El catálogo global no registra todos los sismos pequeños; por debajo de cierta magnitud está incompleto y cualquier ajuste hecho allí quedaría sesgado.

Se estima por **máxima curvatura** (Wiemer & Wyss, 2000) —la magnitud con mayor frecuencia no acumulada— más la corrección estándar de **+0,2**. Si con ese valor quedan menos de 30 eventos, se relaja de a 0,1 hasta obtener una muestra utilizable, y el valor efectivo se publica junto al resultado.

En el suroccidente colombiano Mc ronda 4,6 con el catálogo del USGS. La sismicidad menor —relevante para las fallas corticales andinas— se consulta en el Catálogo Sísmico Integrado del SGC.

## 3. Ley de Gutenberg-Richter

Sobre los eventos con M ≥ Mc:

**Valor b** — máxima verosimilitud (Aki, 1965) con corrección de discretización (Utsu, 1966):

```
b = log10(e) / ( M̄ − (Mc − ΔM/2) )        ΔM = 0,1
```

**Incertidumbre de b** — Shi & Bolt (1982):

```
σ_b = 2,30 · b² · sqrt( Σ(Mi − M̄)² / (n(n−1)) )
```

**Valor a**, normalizado a tasa anual sobre los años cubiertos:

```
a = log10(N / años) + b·Mc
```

La curva frecuencia-magnitud publicada compara el conteo acumulado observado con el ajuste, y marca a partir de qué magnitud el catálogo es completo.

## 4. Recurrencia observada

Para cada umbral de magnitud se publican tres cifras, todas del pasado:

| Cifra | Cómo se obtiene |
|---|---|
| Sismos observados | Conteo directo sobre el catálogo filtrado |
| Tasa anual observada | Conteo ÷ años cubiertos |
| Intervalo medio | Inverso de la tasa anual |

El intervalo medio **no es un calendario**: no implica periodicidad ni abre un plazo de seguridad tras un evento. Cada texto que lo publica lleva esa advertencia. Para decisiones de diseño estructural la referencia es la NSR-10 y el Modelo Nacional de Amenaza Sísmica del SGC, no esta estadística.

## 5. Energía liberada

Relación de Hanks & Kanamori (1979): `log10(E) = 1,5·M + 4,8`, con E en julios, convertida a toneladas equivalentes de TNT (1 t TNT = 4,184·10⁹ J). Es la energía irradiada en ondas sísmicas, no la energía total del proceso de ruptura. La serie mensual está dominada por los pocos eventos mayores, que es lo que corresponde a una escala logarítmica.

## 6. Textos calculados

Cada vista publica cuatro piezas: descripción, interpretación cualitativa (fija), cifras clave (calculadas sobre los datos vigentes) y explicación del método. Las cifras clave se recalculan con cada actualización del catálogo, de modo que el texto nunca contradice a la gráfica. Todos los enunciados van en pasado y ninguno anticipa eventos.

## 7. Limitaciones declaradas

1. **Catálogo global.** El USGS es completo en la región desde M≈4,5; la sismicidad menor no está representada. El catálogo del SGC la cubre mejor y el plugin admite añadirlo como fuente.
2. **Sin homogeneización de magnitudes.** Se usan las escalas tal como las publica el USGS (mb, Mw, ML), comparables para los tamaños que aquí interesan.
3. **Sin declustering.** El catálogo no se descompone en principales y réplicas, así que el valor b puede quedar ligeramente sesgado tras grandes secuencias.
4. **Profundidades por convenio.** Las soluciones que fijan la profundidad (habitualmente 10 km) se cuentan tal cual y pueden acumular eventos en ese valor.
5. **Ventana corta en términos geológicos.** Tres décadas de catálogo no agotan el comportamiento de una zona de subducción capaz de rupturas mucho mayores, como la de 1906.

Estas limitaciones se publican junto a los resultados, no en letra pequeña.

---

## Referencias

- Aki, K. (1965). *Maximum likelihood estimate of b in the formula log N = a − bM.*
- Utsu, T. (1966). *A statistical significance test of the difference in b-value between two earthquake groups.*
- Shi, Y. & Bolt, B. (1982). *The standard error of the magnitude-frequency b value.* BSSA.
- Wiemer, S. & Wyss, M. (2000). *Minimum magnitude of completeness in earthquake catalogs.* BSSA.
- Hanks, T. & Kanamori, H. (1979). *A moment magnitude scale.* JGR.
- U.S. Geological Survey — *FDSN Event Web Service Documentation.*
