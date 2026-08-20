<?php
/**
 * Contenido educativo de preparación ciudadana.
 *
 * Material fijo, revisado contra las guías de la UNGRD, el SGC y la Cruz Roja
 * Colombiana. No contiene cifras dinámicas ni estimaciones: es el componente
 * de «reducción del riesgo» que acompaña al conocimiento del riesgo. El tono
 * es de preparación, no de miedo.
 *
 * @package SismosNarino
 */

defined( 'ABSPATH' ) || exit;

return array(

	'antes' => array(
		'titulo' => 'Antes: prepararse en casa y en el trabajo',
		'intro'  => 'La mayor parte de lo que salva vidas en un sismo se decide antes de que ocurra. Nada de esto exige presupuesto: exige acordarlo en familia y practicarlo.',
		'pasos'  => array(
			'Identifique los sitios seguros de cada espacio: junto a columnas o muros estructurales, lejos de ventanas, vidrios y objetos que puedan caer.',
			'Asegure a la pared los muebles altos, el televisor, los estantes y el calentador. La mayoría de las lesiones en sismos moderados las causan objetos que caen, no el colapso del edificio.',
			'Acuerde un punto de encuentro fuera de la vivienda y un contacto familiar en otra ciudad, porque las líneas locales se saturan.',
			'Tenga a mano el kit de emergencia y revise cada seis meses que lo que contiene siga sirviendo.',
			'Aprenda dónde se cierran el gas, el agua y la electricidad.',
			'Si vive o trabaja en el litoral, aprenda la ruta hacia la zona alta más cercana y camínela al menos una vez.',
			'Si su vivienda tiene daños estructurales previos, grietas en columnas o ampliaciones sin licencia, consulte con la oficina de planeación o el consejo municipal de gestión del riesgo.',
			'Participe en el simulacro nacional: es la forma más barata de descubrir qué falla en su plan.',
		),
	),

	'durante' => array(
		'titulo' => 'Durante: los primeros segundos',
		'intro'  => 'No intente salir corriendo si no está a un paso de la salida: la mayoría de las lesiones ocurren al huir entre objetos que caen. Protéjase donde está.',
		'pasos'  => array(
			'Agáchese, cúbrase la cabeza y el cuello, y sujétese a algo firme hasta que el movimiento termine.',
			'Si está bajo techo, quédese adentro y aléjese de ventanas, vidrios, fachadas y estanterías.',
			'Si está al aire libre, aléjese de fachadas, postes, cables y muros. Los pedazos de fachada son el peligro principal.',
			'Si va conduciendo, deténgase en un lugar despejado, lejos de puentes, taludes y postes, y permanezca dentro del vehículo.',
			'Si usa silla de ruedas, frene, bloquee las ruedas y proteja su cabeza y cuello con los brazos o un cojín.',
			'Si está en la costa y el sismo fue largo o muy fuerte, el propio sismo es la alerta: no espere ninguna señal y suba a la zona alta apenas cese el movimiento.',
		),
	),

	'despues' => array(
		'titulo' => 'Después: las primeras horas',
		'intro'  => 'La emergencia no termina con el sismo. Las horas siguientes concentran las réplicas, los incendios y los accidentes por estructuras debilitadas.',
		'pasos'  => array(
			'Revise si usted o quienes están cerca tienen lesiones y preste primeros auxilios antes de moverse.',
			'Salga con calma por la ruta prevista si la edificación está dañada, y no use ascensores.',
			'Cierre el gas si percibe olor y no encienda fósforos, velas ni interruptores.',
			'Use mensajes de texto en vez de llamadas: liberan la red para la atención de la emergencia.',
			'No regrese a una edificación evacuada hasta que alguien competente la revise.',
			'Espere réplicas y manténgase lejos de lo que quedó debilitado.',
			'Infórmese solo por los canales oficiales del SGC, la UNGRD y las autoridades locales; no comparta cadenas ni cifras sin fuente.',
			'Reporte lo que sintió en «Sismos sentidos» del SGC: los reportes ciudadanos mejoran los mapas de intensidad.',
		),
	),

	'kit' => array(
		'titulo' => 'Kit de emergencia',
		'intro'  => 'Un morral por vivienda, en un lugar accesible y conocido por todos, con lo necesario para al menos 72 horas.',
		'pasos'  => array(
			'Agua: cuatro litros por persona y por día, renovada periódicamente.',
			'Alimentos no perecederos y de consumo directo, y un abrelatas manual.',
			'Botiquín con los medicamentos de uso permanente de la familia y sus fórmulas.',
			'Linterna y radio a pilas, con pilas de repuesto; una batería externa cargada.',
			'Copia de documentos de identidad, seguros y escrituras en bolsa plástica sellada.',
			'Silbato: se oye mucho más lejos que una voz y consume menos energía.',
			'Ropa abrigada, cobija térmica, calzado cerrado y guantes.',
			'Dinero en efectivo en billetes pequeños: los datáfonos y cajeros pueden quedar fuera de servicio.',
			'Elementos de higiene y, si aplica, insumos para bebés, personas mayores o mascotas.',
		),
	),

	'comunidad' => array(
		'titulo' => 'En el colegio, el trabajo y el barrio',
		'intro'  => 'La preparación colectiva multiplica la individual, y en Nariño buena parte de la respuesta inicial la asumen los propios vecinos.',
		'pasos'  => array(
			'Verifique que su colegio o lugar de trabajo tenga plan de emergencia, rutas señalizadas y brigada capacitada.',
			'Practique la evacuación al menos una vez al año, con las personas que realmente estarán allí.',
			'Identifique en su cuadra a quienes necesitarán apoyo: personas mayores, con discapacidad, niñas y niños solos.',
			'Conozca a su consejo municipal de gestión del riesgo y sepa cómo reportar daños.',
			'Si su comunidad está en zona de influencia volcánica, conozca los niveles de actividad que publica el Observatorio Vulcanológico y Sismológico de Pasto y qué implica cada uno.',
		),
	),
);
