-- Relaciona cada enlace opcional con un único comentario de Tareas.
-- Ejecutar una sola vez sobre la base CRM elegida, después de verificar
-- que existe la tabla tarea_enlace y antes de habilitar esta funcionalidad.
-- Los enlaces existentes quedan sin asociación (NULL); no se modifican filas.

ALTER TABLE tarea_enlace
  ADD COLUMN id_tarea_comentario INT UNSIGNED NULL DEFAULT NULL AFTER id_tarea,
  ADD UNIQUE KEY uq_tarea_enlace_comentario (id_tarea_comentario);
