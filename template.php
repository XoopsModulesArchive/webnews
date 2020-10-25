<?php

ob_start();
?>
    <table cellpadding="0" border="0" align="center" width="80%">
        <tr>
            <td>
                <?php
                include $content_page;
                ?>
            </td>
        </tr>
    </table>
<?php
ob_end_flush();
?>
