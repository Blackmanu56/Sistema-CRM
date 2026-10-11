-- Migración del mapa interactivo de eventos de Misiones.
-- Ejecutar una sola vez sobre cada base existente del CRM.

ALTER TABLE localidad
  ADD COLUMN latitud DECIMAL(10,7) NULL AFTER id_provincia,
  ADD COLUMN longitud DECIMAL(10,7) NULL AFTER latitud,
  ADD COLUMN poligono_geojson LONGTEXT NULL AFTER longitud;

ALTER TABLE evento
  ADD COLUMN id_localidad SMALLINT UNSIGNED NULL AFTER id_cliente,
  ADD INDEX idx_evento_localidad_activo (id_localidad, activo),
  ADD CONSTRAINT fk_evento_localidad
    FOREIGN KEY (id_localidad) REFERENCES localidad(id_localidad);

-- Compatibilidad con datos existentes: si el evento tiene un cliente, se usa
-- la localidad registrada para ese cliente hasta que se indique otra sede.
UPDATE evento ev
INNER JOIN cliente c ON c.id_cliente=ev.id_cliente
SET ev.id_localidad=c.id_localidad
WHERE ev.id_localidad IS NULL;

-- Coordenadas de referencia para las localidades incluidas en crm_prueba.sql.
-- Fuente principal: GeoNames Argentina (https://www.geonames.org/);
-- Colonia Alicia y centro de San Vicente: OpenStreetMap / Nominatim
-- (© OpenStreetMap contributors, ODbL).
-- Son puntos representativos de cada localidad, no domicilios de eventos.
UPDATE localidad l
INNER JOIN provincia p ON p.id_provincia=l.id_provincia
SET
	l.latitud=IF(l.latitud IS NULL, CASE l.nombre
		WHEN 'Posadas' THEN -27.3918400
		WHEN 'Garupá' THEN -27.4831500
		WHEN 'Fachinal' THEN -27.6291600
		WHEN 'Apóstoles' THEN -27.9142100
		WHEN 'Azara' THEN -28.0606700
		WHEN 'San José' THEN -27.7691600
		WHEN 'Tres Capones' THEN -28.0043100
		WHEN 'Aristóbulo del Valle' THEN -27.0957100
		WHEN 'Campo Grande' THEN -27.2077000
		WHEN 'Campo Ramón' THEN -27.4549000
		WHEN 'Dos de Mayo' THEN -27.0199800
		WHEN 'Candelaria' THEN -27.4617000
		WHEN 'Bonpland' THEN -27.4834700
		WHEN 'Cerro Corá' THEN -27.5137700
		WHEN 'Loreto' THEN -27.3317700
		WHEN 'Santa Ana' THEN -27.3692400
		WHEN 'Profundidad' THEN -27.5613100
		WHEN 'Mártires' THEN -27.4226500
		WHEN 'Concepción de la Sierra' THEN -27.9822500
		WHEN 'Santa María' THEN -27.9349000
		WHEN 'Eldorado' THEN -26.4045300
		WHEN 'Puerto Piray' THEN -26.4693700
		WHEN 'Colonia Victoria' THEN -26.3312400
		WHEN 'Alicia' THEN -27.3781456
		WHEN 'San Vicente' THEN -26.9932723
		WHEN 'San Antonio' THEN -26.0599300
		WHEN 'Bernardo de Irigoyen' THEN -26.2554300
		WHEN 'Comandante Andresito' THEN -25.6700000
		WHEN 'El Soberbio' THEN -27.2966200
		WHEN 'Puerto Iguazú' THEN -25.5991200
		WHEN 'Wanda' THEN -25.9733700
		WHEN 'Puerto Libertad' THEN -25.9195800
		WHEN 'Leandro N. Alem' THEN -27.6037600
		WHEN 'Almafuerte' THEN -27.5082500
		WHEN 'Cerro Azul' THEN -27.6336300
		WHEN 'Gobernador Roca' THEN -27.1905700
		WHEN 'Olegario V. Andrade' THEN -27.5670500
		WHEN 'Panambí' THEN -27.7219900
		WHEN 'Jardín América' THEN -27.0434600
		WHEN 'Puerto Rico' THEN -26.8089000
		WHEN 'Ruiz de Montoya' THEN -26.9708800
		WHEN 'Capioví' THEN -26.9293600
		WHEN 'Garuhapé' THEN -26.8211600
		WHEN 'Montecarlo' THEN -26.5684900
		WHEN 'Caraguatay' THEN -26.6058700
		WHEN 'Oberá' THEN -27.4868300
		WHEN 'Campo Viera' THEN -27.3327100
		WHEN 'General Alvear' THEN -27.4261100
		WHEN 'Los Helechos' THEN -27.5570000
		WHEN 'San Ignacio' THEN -27.2586500
		WHEN 'Corpus' THEN -27.1294700
		WHEN 'Santo Pipó' THEN -27.1414700
		WHEN 'Hipólito Yrigoyen' THEN -27.0920000
		WHEN 'San Javier' THEN -27.8739800
		WHEN 'Florentino Ameghino' THEN -27.6333500
		WHEN 'San Pedro' THEN -26.6218000
		WHEN 'Paraíso' THEN -26.6870700
		WHEN 'Veinticinco de Mayo' THEN -27.3779600
		ELSE NULL
	END, l.latitud),
	l.longitud=IF(l.longitud IS NULL, CASE l.nombre
		WHEN 'Posadas' THEN -55.9237900
		WHEN 'Garupá' THEN -55.8292400
		WHEN 'Fachinal' THEN -55.7050600
		WHEN 'Apóstoles' THEN -55.7535500
		WHEN 'Azara' THEN -55.6770900
		WHEN 'San José' THEN -55.7803300
		WHEN 'Tres Capones' THEN -55.6093500
		WHEN 'Aristóbulo del Valle' THEN -54.8934400
		WHEN 'Campo Grande' THEN -54.9797700
		WHEN 'Campo Ramón' THEN -55.0212200
		WHEN 'Dos de Mayo' THEN -54.6846200
		WHEN 'Candelaria' THEN -55.7449000
		WHEN 'Bonpland' THEN -55.4778400
		WHEN 'Cerro Corá' THEN -55.6089500
		WHEN 'Loreto' THEN -55.5249900
		WHEN 'Santa Ana' THEN -55.5816700
		WHEN 'Profundidad' THEN -55.7050600
		WHEN 'Mártires' THEN -55.3765900
		WHEN 'Concepción de la Sierra' THEN -55.5208000
		WHEN 'Santa María' THEN -55.4074200
		WHEN 'Eldorado' THEN -54.6184700
		WHEN 'Puerto Piray' THEN -54.7073600
		WHEN 'Colonia Victoria' THEN -54.6214100
		WHEN 'Alicia' THEN -54.3518686
		WHEN 'San Vicente' THEN -54.4866356
		WHEN 'San Antonio' THEN -53.7357800
		WHEN 'Bernardo de Irigoyen' THEN -53.6468200
		WHEN 'Comandante Andresito' THEN -54.0455200
		WHEN 'El Soberbio' THEN -54.1981100
		WHEN 'Puerto Iguazú' THEN -54.5735500
		WHEN 'Wanda' THEN -54.5630400
		WHEN 'Puerto Libertad' THEN -54.5822900
		WHEN 'Leandro N. Alem' THEN -55.3236500
		WHEN 'Almafuerte' THEN -55.4025800
		WHEN 'Cerro Azul' THEN -55.4974600
		WHEN 'Gobernador Roca' THEN -55.4692700
		WHEN 'Olegario V. Andrade' THEN -55.5020800
		WHEN 'Panambí' THEN -54.9148600
		WHEN 'Jardín América' THEN -55.2269800
		WHEN 'Puerto Rico' THEN -55.0214700
		WHEN 'Ruiz de Montoya' THEN -55.0506700
		WHEN 'Capioví' THEN -55.0577300
		WHEN 'Garuhapé' THEN -54.9577400
		WHEN 'Montecarlo' THEN -54.7579000
		WHEN 'Caraguatay' THEN -54.7809300
		WHEN 'Oberá' THEN -55.1199400
		WHEN 'Campo Viera' THEN -55.0565100
		WHEN 'General Alvear' THEN -55.1691600
		WHEN 'Los Helechos' THEN -55.0760000
		WHEN 'San Ignacio' THEN -55.5389700
		WHEN 'Corpus' THEN -55.5108300
		WHEN 'Santo Pipó' THEN -55.4094500
		WHEN 'Hipólito Yrigoyen' THEN -55.2860000
		WHEN 'San Javier' THEN -55.1348500
		WHEN 'Florentino Ameghino' THEN -55.0837200
		WHEN 'San Pedro' THEN -54.1090200
		WHEN 'Paraíso' THEN -54.2030700
		WHEN 'Veinticinco de Mayo' THEN -54.7450500
		ELSE NULL
	END, l.longitud)
WHERE p.nombre='Misiones'
	AND (l.latitud IS NULL OR l.longitud IS NULL);

-- El archivo de límites municipales se coloca en:
-- public/general/mapa-interactivo/geojson/misiones-municipios.geojson
