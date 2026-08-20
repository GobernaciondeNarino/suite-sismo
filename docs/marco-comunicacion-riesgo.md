# Marco de comunicación del riesgo: por qué esta plataforma no pronostica sismos

**Plugin:** Sismos Nariño · **Versión:** 2.0.0 · **Revisión:** 2026-08-19
**Aplica a:** todo componente publicable del plugin y a cualquier contribución futura.

---

## 1. La decisión

La plataforma **no publica pronósticos de sismos**: ni predicciones, ni probabilidades propias de ocurrencia futura, ni estimaciones de réplicas. Publica **conocimiento del riesgo**: estadística de lo ya ocurrido, contexto de amenaza, preparación ciudadana y enlaces a la autoridad técnica.

Esta decisión es institucional, no una preferencia de implementación. Por eso está respaldada por una prueba automática (`tests/test-sin-pronostico.php`) que falla si el módulo reaparece.

## 2. Los fundamentos

**La predicción determinística no es posible.** El USGS lo afirma sin ambigüedad: nadie ha predicho nunca un sismo mayor, no se sabe cómo hacerlo y no se espera saberlo en el futuro previsible. Una predicción real exige tres elementos —fecha y hora, lugar y magnitud— que ninguna metodología entrega. El SGC sostiene lo mismo: «la ciencia aún no puede predecir los sismos».

**El pronóstico probabilístico es competencia del SGC, y hoy no lo emite.** El pronóstico de réplicas sí es un producto científico legítimo —el USGS y GeoNet lo publican—, pero en Colombia la autoridad técnica es el Servicio Geológico Colombiano (Ley 4131 de 2011, Ley 1523 de 2012, Decreto 2703 de 2013). El SGC reporta y cuenta réplicas **después** de que ocurren; no publica probabilidades. Una gobernación no puede erigirse en fuente de un producto técnico que la autoridad nacional no emite.

**La comunicación defectuosa tiene consecuencias.** En L'Aquila (2009) los científicos no fueron juzgados por no predecir el sismo, sino por comunicar mal el riesgo con mensajes falsamente tranquilizadores. El estándar ético que se sigue aquí es el que dejó ese caso: ni alarmismo ni falsa tranquilidad; comunicar la incertidumbre con honestidad.

**La desinformación es un riesgo real y actual.** Tras el sismo de magnitud 7,4 del 10 de agosto de 2026 en San José del Palmar (Chocó) circularon cadenas y comunicados falsos —algunos con sellos de apariencia oficial y videos generados con IA— anunciando réplicas con fecha y hora. El SGC, el IDEAM y las autoridades ecuatorianas los desmintieron. Una cifra publicada por la Gobernación y malinterpretada como anticipación alimentaría exactamente ese circuito.

## 3. Los cuatro conceptos que no deben confundirse

| Concepto | ¿Es posible? | Qué es | Quién lo hace |
|---|---|---|---|
| **Alerta temprana** | Sí | Detecta un sismo que ya empezó y avisa segundos antes de que lleguen las ondas | ShakeAlert (EE. UU.), SASMEX (México), Japón. Colombia no tiene sistema público |
| **Pronóstico** | Sí | Probabilidad de cierto número de sismos en un periodo; el caso maduro son las réplicas | USGS, GeoNet. **El SGC no lo emite; esta plataforma tampoco** |
| **Probabilidad de largo plazo** | Sí | Mapas de amenaza: probabilidad de excedencia de una intensidad en, típicamente, 50 años | SGC — Modelo Nacional de Amenaza Sísmica. **La plataforma enlaza, no calcula** |
| **Predicción** | **No** | Fecha, lugar y magnitud de un sismo futuro | Nadie. Toda «predicción» que circule es falsa |

Este cuadro se publica de cara al público con el shortcode `[sismos_glosario]`.

## 4. Qué sí publica la plataforma

1. **Estadística retrospectiva del catálogo** (USGS): sismicidad por mes y por año, distribución de magnitudes y profundidades, energía liberada, relación de Gutenberg-Richter y **recurrencia observada** por umbral de magnitud. Todo describe lo ocurrido en la ventana consultada.
2. **Contexto de amenaza** (`[sismos_amenaza]`): subducción Nazca/Malpelo frente al Pacífico, fallas activas continentales, vulcanismo y amenaza por tsunami, con la referencia normativa NSR-10 y enlace al sistema de consulta de amenaza del SGC.
3. **Glosario** (`[sismos_glosario]`): el cuadro de la sección anterior.
4. **Guía post-sismo** (`[sismos_replicas]`): qué son las réplicas, cuánto duran y qué hacer. Texto fijo, sin cifras propias, con enlace al boletín oficial.
5. **Panel anti-desinformación** (`[sismos_desinformacion]`): cómo reconocer una predicción falsa.
6. **Preparación ciudadana** (`[sismos_preparacion]`): antes, durante, después, kit y organización comunitaria.
7. **Directorio oficial** (`[sismos_fuentes_oficiales]`) y **descargo institucional** en todos los componentes de amenaza.

## 5. Reglas de redacción para cualquier contribución

**Prohibido**
- Anunciar sismos o réplicas, con o sin fecha.
- Publicar probabilidades propias de ocurrencia futura, aunque estén bien calculadas.
- Cuentas regresivas, «el próximo gran sismo», mapas con «fechas probables».
- Cualquier elemento que sugiera que la plataforma avisará antes de un sismo.
- Cifras de víctimas o de daños sin fuente oficial.

**Obligatorio**
- Enunciar la estadística en pasado: «se registraron», «ocurrió», «en promedio ocurrió uno cada N años».
- Acompañar toda cifra de recurrencia con la advertencia de que es un promedio del pasado y no un calendario.
- Citar y enlazar la fuente en cada componente.
- Mantener visible el descargo: la autoridad técnica es el SGC.

## 6. Qué cambiaría esta política

- **Si el SGC publicara oficialmente pronósticos operativos de réplicas**, la plataforma podría *replicar o enlazar* ese producto oficial —nunca generarlo—. Hasta entonces, no se muestran probabilidades de réplicas.
- **Si se completa la microzonificación sísmica de Pasto**, se incorporarían sus mapas como capa de mayor detalle.
- **Si se adopta por decreto la actualización AIS 100-24** en reemplazo de la NSR-10, deben actualizarse los coeficientes y la nota de vigencia en *Sismos Nariño → Amenaza y normativa*.

## 7. La salvaguarda técnica

`tests/test-sin-pronostico.php` recorre el código publicado y falla si reaparecen identificadores del módulo retirado (clases, vistas, rutas, shortcodes) o frases que anuncien sismos futuros con cifras. Se ejecuta con:

```bash
php tests/test-sin-pronostico.php
```

Los dos test de análisis y de vistas comprueban además que ninguna vista publica meses futuros, que todas llevan el aviso de alcance y que la estadística no expone ningún método de probabilidad hacia adelante.

---

## Referencias

- U.S. Geological Survey. *Can you predict earthquakes?* (FAQ oficial).
- Servicio Geológico Colombiano. Red Sismológica Nacional; Modelo Nacional de Amenaza Sísmica (SGC – Fundación GEM, 2020); declaraciones tras el sismo del 10 de agosto de 2026.
- Ley 1523 de 2012 (política nacional de gestión del riesgo: conocimiento, reducción y manejo).
- NSR-10, Decreto 926 de 2010; proyecto de actualización AIS 100-24 (no adoptado a la fecha de esta revisión).
- Reasenberg & Jones (1989, 1994); GeoNet/GNS Science (Nueva Zelanda); J-SHIS (Japón); SASMEX (México) — referentes de pronóstico y alerta operados por la autoridad competente de cada país.
- Caso L'Aquila (2009–2015) como precedente de responsabilidad en comunicación de riesgo.
