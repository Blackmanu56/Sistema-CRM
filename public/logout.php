<?php
require_once __DIR__.'/_init.php';
session_unset();
session_destroy();
?>
<script language="javascript">
document.location="index.php";
</script>
