-- Datos SINTÉTICOS para pruebas (ISO 27001 A.8.33 / ASC-24). No contiene datos reales.
CREATE TABLE restautantes (id INTEGER PRIMARY KEY, rut TEXT, nombre TEXT, fono TEXT, mapa TEXT, foto TEXT, email TEXT,
  creado TEXT, actualizado TEXT, eliminado TEXT, direcciones_id INTEGER);
CREATE TABLE direcciones (id INTEGER PRIMARY KEY, calle TEXT, numero TEXT, comuna TEXT, region TEXT, creado TEXT, actualizado TEXT, eliminado TEXT);
CREATE TABLE cartas (id INTEGER PRIMARY KEY, nombre TEXT, orden INTEGER, visible INTEGER, foto TEXT, creada TEXT, actualizada TEXT, eliminada TEXT, restautantes_id INTEGER);
CREATE TABLE categorias (id INTEGER PRIMARY KEY, nombre TEXT, visible INTEGER, foto TEXT, orden INTEGER, creado TEXT, actualizado TEXT, eliminado TEXT, cartas_id INTEGER);
CREATE TABLE items (id INTEGER PRIMARY KEY, nombre TEXT, descripcion TEXT, tiempo TEXT, observaciones TEXT, precio INTEGER, visible INTEGER,
  destacado INTEGER, puntuacion INTEGER, foto TEXT, orden INTEGER, creado TEXT, actualizado TEXT, eliminado TEXT, categorias_id INTEGER);
CREATE TABLE comentarios (Id INTEGER PRIMARY KEY AUTOINCREMENT, usuario TEXT, usuario_id INTEGER, comentario TEXT, id_restaurante INTEGER, creado TEXT);
CREATE TABLE usuarios (Id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, email TEXT UNIQUE NOT NULL, password TEXT NOT NULL, estado TEXT DEFAULT '1', totp_secret TEXT DEFAULT NULL);
CREATE TABLE login_intentos (id INTEGER PRIMARY KEY AUTOINCREMENT, email_hash TEXT NOT NULL, ip TEXT NOT NULL, fecha TEXT NOT NULL);

INSERT INTO direcciones VALUES (1,'Calle Ficticia','100','La Serena','Coquimbo',NULL,NULL,NULL);
INSERT INTO restautantes VALUES (1,'11111111-1','Resto Prueba','000000',NULL,'resto1.jpg','contacto@example.com',NULL,NULL,NULL,1);
INSERT INTO restautantes VALUES (2,'22222222-2','Resto Eliminado','000000',NULL,NULL,'x@example.com',NULL,NULL,'2020-01-01',1);
INSERT INTO cartas VALUES (1,'Platos Fondo',1,1,NULL,NULL,NULL,NULL,1);
INSERT INTO categorias VALUES (1,'Carnes',1,NULL,1,NULL,NULL,NULL,1);
INSERT INTO items VALUES (1,'Lomo <b>Prueba</b>','Desc','10 min',NULL,9900,1,1,4,'item1.jpg',1,NULL,NULL,NULL,1);
INSERT INTO items VALUES (2,'Item Oculto','Desc',NULL,NULL,1000,0,0,0,NULL,2,NULL,NULL,NULL,1);
