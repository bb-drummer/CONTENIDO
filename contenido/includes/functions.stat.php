<?php

/**
 * This file contains the CONTENIDO statistic functions.
 *
 * @package    Core
 * @subpackage Backend
 * @author     Olaf Niemann
 * @copyright  four for business AG <www.4fb.de>
 * @license    https://www.contenido.org/license/LIZENZ.txt
 * @link       https://www.4fb.de
 * @link       https://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

cInclude('includes', 'functions.database.php');

/**
 * Displays statistic information layer (a div Tag)
 *
 * @deprecated [2015-05-21]
 *         This method is no longer supported (no replacement)
 *
 * @param int    $id
 *         Either article or directory id
 * @param string $type
 *         The type
 * @param int    $x
 *         Style top position
 * @param int    $y
 *         Style left position
 * @param int    $w
 *         Style width
 * @param int    $h
 *         Style height
 *
 * @return string
 *         Composed info layer
 *
 * @throws cException
 * @throws cInvalidArgumentException
 */
function statsDisplayInfo($id, $type, $x, $y, $w, $h)
{
    cDeprecated('This method is deprecated and is not needed any longer');

    if (strcmp($type, 'article' == 0)) {
        $text = i18n("Info about article") . " " . $id;
    } else {
        $text = i18n("Info about directory") . " " . $id;
    }

    $div = new cHTMLDiv($text, 'text_medium', 'idElement14');
    $div->appendStyleDefinition('border', '1px solid #e8e8ee');
    $div->appendStyleDefinition('position', 'absolute');
    $div->appendStyleDefinition('top', $x . 'px');
    $div->appendStyleDefinition('left', $y . 'px');
    $div->appendStyleDefinition('width', $w . 'px');
    $div->appendStyleDefinition('height', $h . 'px');

    return $div->toHtml();
}

/**
 * Archives the current statistics
 *
 * @param string $yearMonth
 *         String with the desired archive date (YYYYMM)
 *
 * @throws cDbException|cInvalidArgumentException
 */
function statsArchive($yearMonth)
{
    $yearMonth = preg_replace('/\s/', '0', $yearMonth);

    $db = cRegistry::getDb();
    $db2 = cRegistry::getDb();

    $sql = 'SELECT `idcatart`, `idlang`, `idclient`, `visited`, `visitdate` FROM `%s`';
    $db->query($sql, cRegistry::getDbTableName('stat'));

    while ($db->nextRecord()) {
        $insertSQL = $db2->buildInsert(cRegistry::getDbTableName('stat_archive'), [
            'archived' => $yearMonth,
            'idcatart' => cSecurity::toInteger($db->f(0)),
            'idlang' => cSecurity::toInteger($db->f(1)),
            'idclient' => cSecurity::toInteger($db->f(2)),
            'visited' => cSecurity::toInteger($db->f(3)),
            'visitdate' => $db->f(4)
        ]);
        $db2->query($insertSQL);
    }

    $sql = 'TRUNCATE TABLE `%s`';
    $db->query($sql, cRegistry::getDbTableName('stat'));

    // Recreate empty stats
    $sql = 'SELECT
                A.idcatart, B.idclient, C.idlang
            FROM
                `%s` AS A INNER JOIN
                `%s` AS B ON A.idcat = B.idcat INNER JOIN
                `%s` AS C ON A.idcat = C.idcat ';
    $db->query(
        $sql, cRegistry::getDbTableName('cat_art'),
        cRegistry::getDbTableName('cat'), cRegistry::getDbTableName('cat_lang')
    );

    while ($db->nextRecord()) {
        $insertSQL = $db2->buildInsert(cRegistry::getDbTableName('stat'), [
            'idcatart' => cSecurity::toInteger($db->f(0)),
            'idlang' => cSecurity::toInteger($db->f(2)),
            'idclient' => cSecurity::toInteger($db->f(1)),
            'visited' => 0,
        ]);
        $db2->query($insertSQL);
    }
}

/**
 * Generates a statistics page
 *
 * @param string $yearMonth
 *         Specifies the year and month from which to retrieve the statistics,
 *         specify "current" to retrieve the current entries.
 *
 * @throws cDbException
 * @throws cException
 */
function statsOverviewAll($yearMonth)
{
    global $tpl;

    $db = cRegistry::getDb();
    $cfg = cRegistry::getConfig();
    $client = cSecurity::toInteger(cRegistry::getClientId());
    $lang = cSecurity::toInteger(cRegistry::getLanguageId());

    $sDisplay = 'table-row';
    $bUseHeapTable = $cfg['statistics_heap_table'];
    $sHeapTable = cRegistry::getDbTableName('stat_heap_table');

    if ($bUseHeapTable) {
        if (!dbTableExists($db, $sHeapTable)) {
            buildHeapTable($sHeapTable, $db);
        }
    }

    if (stripos($_SERVER['HTTP_USER_AGENT'] ?? '', 'MSIE') !== false) {
        $sDisplay = 'block';
    }

    $sql = 'SELECT
                    idtree, A.idcat, level, preid, C.name, visible
                FROM
                    `:tab_cat_tree` AS A,
                    `:tab_cat` AS B,
                    `:tab_cat_lang` AS C
                WHERE
                    A.idcat = B.idcat AND
                    B.idcat = C.idcat AND
                    C.idlang = :idlang AND
                    B.idclient = :idclient
                ORDER BY idtree';

    $db->query($sql, [
        'tab_cat_tree' => cRegistry::getDbTableName('cat_tree'),
        'tab_cat' => cRegistry::getDbTableName('cat'),
        'tab_cat_lang' => cRegistry::getDbTableName('cat_lang'),
        'idlang' => $lang,
        'idclient' => $client,
    ]);

    $currentRow = 2;

    $aRowNames = [];
    $iLevel = 0;
    $backendUrl = cRegistry::getBackendUrl();
    $tpl->set('s', 'IMG_EXPAND', $backendUrl . $cfg['path']['images'] . 'open_all.gif');
    $tpl->set('s', 'IMG_COLLAPSE', $backendUrl . $cfg['path']['images'] . 'close_all.gif');

    $sumNumberOfArticles = 0;

    while ($db->nextRecord()) {
        if ($db->f('level') == 0 && $db->f("preid") != 0) {
            $tpl->set('d', 'PADDING_LEFT', '10');
            $tpl->set('d', 'TEXT', '&nbsp;');
            $tpl->set('d', 'NUMBEROFARTICLES', '');
            $tpl->set('d', 'TOTAL', '');
            $tpl->set('d', 'ICON', '');
            $tpl->set('d', 'STATUS', '');
            $tpl->set('d', 'ROWNAME', '');
            $tpl->set('d', 'INTHISLANGUAGE', '');
            $tpl->set('d', 'EXPAND', '');
            $tpl->set('d', 'DISPLAY_ROW', $sDisplay);
            $tpl->set('d', 'PATH', '');
            $tpl->set('d', 'ULR_TO_PAGE', '');

            $tpl->next();
            $currentRow++;
        }

        $paddingLeft = 10 + (15 * $db->f('level'));
        $text = $db->f(4);
        $idcat = cSecurity::toInteger($db->f('idcat'));
        $bCatVisible = $db->f("visible");

        if ($db->f('level') < $iLevel) {
            $iDistance = $iLevel - $db->f('level');

            for ($i = 0; $i < $iDistance; $i++) {
                array_pop($aRowNames);
            }
            $iLevel = $db->f('level');
        }

        if ($db->f('level') >= $iLevel) {
            if ($db->f('level') == $iLevel) {
                array_pop($aRowNames);
            } else {
                $iLevel = $db->f('level');
            }
            $aRowNames[] = $idcat;
        }

        // number of arts
        $sql = "SELECT COUNT(*) FROM `%s` WHERE idcat = %d";
        $db2 = cRegistry::getDb();
        $db2->query($sql, cRegistry::getDbTableName('cat_art'), $idcat);
        $db2->nextRecord();
        $numberOfArticles = $db2->f(0);
        $sumNumberOfArticles += $numberOfArticles;

        // hits of category total
        if (strcmp($yearMonth, "current") == 0) {
            $sql = "SELECT SUM(visited) FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " .cRegistry::getDbTableName('stat') . " AS B WHERE A.idcatart=B.idcatart AND A.idcat=" . $idcat . " AND B.idclient=" . $client;
        } else {
            if (!$bUseHeapTable) {
                $sql = "SELECT SUM(visited) FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat_archive') . " AS B WHERE A.idcatart=B.idcatart AND A.idcat=" . $idcat . "
                        AND B.idclient=" . $client . " AND B.archived='" . $db2->escape($yearMonth) . "'";
            } else {
                $sql = "SELECT SUM(visited) FROM " . $db2->escape($sHeapTable) . " WHERE idcat=" . $idcat . "
                        AND idclient=" . $client . " AND archived='" . $db2->escape($yearMonth) . "'";
            }
        }
        $db2->query($sql);
        $db2->nextRecord();
        $total = $db2->f(0);

        // hits of category in this language
        if (strcmp($yearMonth, "current") == 0) {
            $sql = "SELECT SUM(visited) FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat') . " AS B WHERE A.idcatart=B.idcatart AND A.idcat=" . $idcat . "
                    AND B.idlang=" . $lang . " AND B.idclient=" . $client;
        } else {
            if (!$bUseHeapTable) {
                $sql = "SELECT SUM(visited) FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat_archive') . " AS B WHERE A.idcatart=B.idcatart AND A.idcat=" . $idcat  . "
                        AND B.idlang=" . $lang . " AND B.idclient=" . $client . " AND B.archived='" . $db2->escape($yearMonth) . "'";
            } else {
                $sql = "SELECT SUM(visited) FROM " . $db2->escape($sHeapTable) . " WHERE idcat=" . $idcat . " AND idlang=" . $lang . "
                        AND idclient=" . $client . " AND archived='" . $db2->escape($yearMonth) . "'";
            }
        }
        $db2->query($sql);
        $db2->nextRecord();
        $inThisLanguage = $db2->f(0);

        $icon = '<img alt="" src="' . $cfg['path']['images'] . 'folder.gif" class="vAlignMiddle">';

        // art
        $sql = "SELECT * FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('art') . " AS B, " . cRegistry::getDbTableName('art_lang') . " AS C WHERE A.idcat=" . $idcat . "
                AND A.idart=B.idart AND B.idart=C.idart AND C.idlang=" . $lang . " ORDER BY B.idart";
        $db2->query($sql);

        $numRows = $db2->numRows();

        $online = $db->f("visible");
        if ($bCatVisible == 1) {
            $offOnline = '<img src="' . $cfg['path']['images'] . 'online_off.gif" alt="' . i18n("Category is online") . '" title="' . i18n("Category is online") . '">';
        } else {
            $offOnline = '<img src="' . $cfg['path']['images'] . 'offline_off.gif" alt="' . i18n("Category is offline") . '" title="' . i18n("Category is offline") . '">';
        }

        // check if there are subcategories
        $iSumSubCategories = 0;
        $sSql = "SELECT COUNT(*) AS cat_count FROM " . cRegistry::getDbTableName('cat') . " WHERE parentid=" . $idcat . ";";
        $db3 = cRegistry::getDb();
        $db3->query($sSql);
        if ($db3->nextRecord()) {
            $iSumSubCategories = $db3->f('cat_count');
        }
        $db3->free();

        $tpl->set('d', 'PADDING_LEFT', $paddingLeft);
        $tpl->set('d', 'TEXT', conHtmlSpecialChars($text) . ' (idcat: ' . cSecurity::toInteger($db->f('idcat')) . ')');
        $tpl->set('d', 'ICON', $icon);
        $tpl->set('d', 'STATUS', $offOnline);
        $tpl->set('d', 'NUMBEROFARTICLES', $numberOfArticles);
        $tpl->set('d', 'TOTAL', $total);
        $tpl->set('d', 'ROWNAME', implode('_', $aRowNames));
        if ($numRows > 0 || $iSumSubCategories > 0) {
            $tpl->set('d', 'EXPAND', '<a href="javascript:changeVisibility(\'' . implode('_', $aRowNames) . '\', ' . $db->f('level') . ', ' . $idcat . ')">
                                          <img src="' . $cfg['path']['images'] . 'open_all.gif"
                                               alt="' . i18n("Open category") . '"
                                               title="' . i18n("Open category") . '"
                                               id="' . implode('_', $aRowNames) . '_img"
                                               class="vAlignMiddle">
                                      </a>');
        } else {
            $tpl->set('d', 'EXPAND', '<img alt="" src="' . $cfg['path']['images'] . 'spacer.gif" width="7">');
        }
        $tpl->set('d', 'INTHISLANGUAGE', $inThisLanguage);
        if ($db->f('level') != 0) {
            $tpl->set('d', 'DISPLAY_ROW', 'none');
        } else {
            $tpl->set('d', 'DISPLAY_ROW', $sDisplay);
        }
        $frontendURL = cRegistry::getFrontendUrl();
        $catName = '';
        statCreateLocationString($db->f('idcat'), '&nbsp;/&nbsp;', $catName);
        $tpl->set('d', 'PATH', i18n("Path") . ':&nbsp;/&nbsp;' . $catName);
        $tpl->set('d', 'ULR_TO_PAGE', $frontendURL . 'front_content.php?idcat=' . $db->f('idcat'));

        $tpl->next();
        $currentRow++;

        while ($db2->nextRecord()) {
            $idart = cSecurity::toInteger($db2->f('idart'));

            $aRowNames[] = $idart;

            $numberOfArticles = '';

            $paddingLeft = 10 + (15 * ($db->f('level') + 1));

            $text = $db2->f('title');
            $online = $db2->f('online');

            // number of arts
            $db3 = cRegistry::getDb();

            // hits of art total
            if (strcmp($yearMonth, "current") == 0) {
                $sql = "SELECT SUM(visited) FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat') . " AS B WHERE A.idcatart=B.idcatart AND A.idcat=" . $idcat . "
                     AND A.idart=" . $idart . " AND B.idclient=" . $client;
            } else {
                if (!$bUseHeapTable) {
                    $sql = "SELECT SUM(visited) FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat_archive') . " AS B WHERE A.idcatart=B.idcatart AND A.idcat=" . $idcat . "
                            AND A.idart=" . $idart . " AND B.idclient=" . $client . " AND B.archived='" . $db3->escape($yearMonth) . "'";
                } else {
                    $sql = "SELECT SUM(visited) FROM " . $db3->escape($sHeapTable) . " WHERE idcat=" . $idcat . " AND idart=" . $idart . "
                            AND idclient=" . $client . " AND archived='" . $db3->escape($yearMonth) . "'";
                }
            }

            $db3->query($sql);
            $db3->nextRecord();

            $total = $db3->f(0);

            // hits of art in this language
            if (strcmp($yearMonth, "current") == 0) {
                $sql = "SELECT visited, idart FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat') . " AS B WHERE A.idcatart=B.idcatart AND A.idcat=" . $idcat . "
                        AND A.idart=" . $idart . " AND B.idlang=" . $lang . " AND B.idclient=" . $client;
            } else {
                if (!$bUseHeapTable) {
                    $sql = "SELECT visited, idart FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat_archive') . " AS B WHERE A.idcatart=B.idcatart AND A.idcat=" . $idcat . "
                            AND A.idart=" . $idart . " AND B.idlang=" . $lang . " AND B.idclient=" . $client . "
                            AND B.archived='" . $db3->escape($yearMonth) . "'";
                } else {
                    $sql = "SELECT visited, idart FROM " . $db3->escape($sHeapTable) . " WHERE idcat=" . $idcat . " AND idart=" . $idart . "
                            AND idlang=" . $lang . " AND idclient=" . $client . " AND archived='" . $db3->escape($yearMonth) . "'";
                }
            }

            $db3->query($sql);
            $db3->nextRecord();

            $inThisLanguage = $db3->f(0);

            if ($online == 0) {
                $offOnline = '<img src="' . $cfg['path']['images'] . 'offline_off.gif" alt="' . i18n("Article is offline") . '" title="' . i18n("Article is offline") . '">';
            } else {
                $offOnline = '<img src="' . $cfg['path']['images'] . 'online_off.gif" alt="' . i18n("Article is online") . '" title="' . i18n("Article is online") . '">';
            }

            $icon = '<img alt="" src="' . $cfg['path']['images'] . 'article.gif"  class="vAlignMiddle">';
            $tpl->set('d', 'PADDING_LEFT', $paddingLeft);
            $tpl->set('d', 'TEXT', conHtmlSpecialChars($text) . ' (idart: ' . cSecurity::toInteger($db3->f('idart')) . ')');
            $tpl->set('d', 'ICON', $icon);
            $tpl->set('d', 'STATUS', $offOnline);
            $tpl->set('d', 'ROWNAME', implode('_', $aRowNames));
            //$tpl->set('d', 'ROWNAME', "HIDE".($db->f('level')+1));
            $tpl->set('d', 'NUMBEROFARTICLES', $numberOfArticles);
            $tpl->set('d', 'TOTAL', $total);
            $tpl->set('d', 'INTHISLANGUAGE', $inThisLanguage);
            $tpl->set('d', 'EXPAND', '<img alt="" src="' . $cfg['path']['images'] . 'spacer.gif" width="7">');
            $tpl->set('d', 'DISPLAY_ROW', 'none');
            $catName = '';
            statCreateLocationString($db3->f('idart'), '&nbsp;/&nbsp;', $catName);
            $tpl->set('d', 'PATH', i18n("Path") . ':&nbsp;/&nbsp;' . $catName);
            $tpl->set('d', 'ULR_TO_PAGE', $frontendURL . 'front_content.php?idart=' . $db3->f('idart'));
            $tpl->next();
            $currentRow++;

            array_pop($aRowNames);
        }
    }

    // hits total
    if (strcmp($yearMonth, "current") == 0) {
        $sql = "SELECT SUM(visited) FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat') . " AS B WHERE A.idcatart=B.idcatart AND B.idclient=" . $client;
    } else {
        if (!$bUseHeapTable) {
            $sql = "SELECT SUM(visited) FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat_archive') . " AS B WHERE A.idcatart=B.idcatart AND B.idclient=" . $client . "
                    AND B.archived='" . $db->escape($yearMonth) . "'";
        } else {
            $sql = "SELECT SUM(visited) FROM " . $db->escape($sHeapTable) . " WHERE idclient=" . $client . " AND archived='" . $db->escape($yearMonth) . "'";
        }
    }

    $db->query($sql);
    $db->nextRecord();

    $total = $db->f(0);

    // hits total on this language
    if (strcmp($yearMonth, "current") == 0) {
        $sql = "SELECT SUM(visited) FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat') . " AS B WHERE A.idcatart=B.idcatart AND B.idlang=" . $lang . "
                AND B.idclient=" . $client;
    } else {
        if (!$bUseHeapTable) {
            $sql = "SELECT SUM(visited) FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat_archive') . " AS B WHERE A.idcatart=B.idcatart AND B.idlang=" . $lang . "
                    AND B.idclient=" . $client . " AND B.archived='" . $db->escape($yearMonth) . "'";
        } else {
            $sql = "SELECT SUM(visited) FROM " . $db->escape($sHeapTable) . " WHERE idlang=" . $lang . " AND idclient=" . $client . "
                    AND archived='" . $db->escape($yearMonth) . "'";
        }
    }

    $db->query($sql);
    $db->nextRecord();

    $inThisLanguage = $db->f(0);

    $tpl->set('d', 'TEXT', '&nbsp;');
    $tpl->set('d', 'ICON', '');
    $tpl->set('d', 'STATUS', '');
    $tpl->set('d', 'PADDING_LEFT', '10');
    $tpl->set('d', 'NUMBEROFARTICLES', '');
    $tpl->set('d', 'TOTAL', '');
    $tpl->set('d', 'INTHISLANGUAGE', '');
    $tpl->set('d', 'EXPAND', '');
    $tpl->set('d', 'ROWNAME', '');
    $tpl->set('d', 'DISPLAY_ROW', $sDisplay);

    $tpl->set('s', 'SUMTEXT', i18n("Sum"));
    $tpl->set('s', 'SUMNUMBEROFARTICLES', $sumNumberOfArticles);
    $tpl->set('s', 'SUMTOTAL', $total);
    $tpl->set('s', 'SUMINTHISLANGUAGE', $inThisLanguage);
    $tpl->next();
}

/**
 * Generates a statistics page for a given year
 *
 * @param string $year
 *         Specifies the year to retrieve the statistics for
 *
 * @throws cDbException
 * @throws cException
 */
function statsOverviewYear($year)
{
    global $tpl;

    $db = cRegistry::getDb();
    $cfg = cRegistry::getConfig();
    $client = cSecurity::toInteger(cRegistry::getClientId());
    $lang = cSecurity::toInteger(cRegistry::getLanguageId());

    $sDisplay = 'table-row';

    if (stripos($_SERVER['HTTP_USER_AGENT'] ?? '', 'MSIE') !== false) {
        $sDisplay = 'block';
    }

    $sql = "SELECT
                idtree, A.idcat, level, preid, C.name, visible
            FROM
                " . cRegistry::getDbTableName('cat_tree') . " AS A,
                " . cRegistry::getDbTableName('cat') . " AS B,
                " . cRegistry::getDbTableName('cat_lang') . " AS C
            WHERE
                A.idcat=B.idcat AND
                B.idcat=C.idcat AND
                C.idlang=" . $lang . " AND
                B.idclient=" . $client . "
            ORDER BY idtree";

    $db->query($sql);

    $currentRow = 2;

    $aRowNames = [];
    $iLevel = 0;
    $backendUrl = cRegistry::getBackendUrl();
    $tpl->set('s', 'IMG_EXPAND', $backendUrl . $cfg['path']['images'] . 'open_all.gif');
    $tpl->set('s', 'IMG_COLLAPSE', $backendUrl . $cfg['path']['images'] . 'close_all.gif');

    $sumNumberOfArticles = 0;

    while ($db->nextRecord()) {
        if ($db->f('level') == 0 && $db->f("preid") != 0) {
            $tpl->set('d', 'PADDING_LEFT', '10');
            $tpl->set('d', 'TEXT', '&nbsp;');
            $tpl->set('d', 'NUMBEROFARTICLES', '');
            $tpl->set('d', 'TOTAL', '');
            $tpl->set('d', 'STATUS', '');
            $tpl->set('d', 'ICON', '');
            $tpl->set('d', 'INTHISLANGUAGE', '');
            $tpl->set('d', 'EXPAND', '');
            $tpl->set('d', 'DISPLAY_ROW', $sDisplay);
            $tpl->set('d', 'ROWNAME', '');
            $tpl->next();
            $currentRow++;
        }

        $paddingLeft = 10 + (15 * $db->f('level'));
        $text = $db->f(4);
        $idcat = cSecurity::toInteger($db->f('idcat'));
        $bCatVisible = $db->f("visible");

        if ($db->f('level') < $iLevel) {
            $iDistance = $iLevel - $db->f('level');

            for ($i = 0; $i < $iDistance; $i++) {
                array_pop($aRowNames);
            }
            $iLevel = $db->f('level');
        }

        if ($db->f('level') >= $iLevel) {
            if ($db->f('level') == $iLevel) {
                array_pop($aRowNames);
            } else {
                $iLevel = $db->f('level');
            }
            $aRowNames[] = $idcat;
        }

        $db2 = cRegistry::getDb();
        // number of arts
        $sql = "SELECT COUNT(*) FROM " . cRegistry::getDbTableName('cat_art') . " WHERE idcat=" . $idcat;
        $db2->query($sql);
        $db2->nextRecord();

        $numberOfArticles = $db2->f(0);
        $sumNumberOfArticles += $numberOfArticles;
        $sql = "SELECT SUM(visited) FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat_archive') . " AS B WHERE A.idcatart=B.idcatart AND A.idcat=" . $idcat . "
                AND B.idclient=" . $client . " AND SUBSTRING(B.archived,1,4)=" . cSecurity::toInteger($year) . " GROUP BY SUBSTRING(B.archived,1,4)";
        $db2->query($sql);
        $db2->nextRecord();

        $total = $db2->f(0);

        // hits of category in this language
        $sql = "SELECT SUM(visited) FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat_archive') . " AS B WHERE A.idcatart=B.idcatart AND A.idcat=" . $idcat . "
                AND B.idlang=" . $lang . " AND B.idclient=" . $client . " AND SUBSTRING(B.archived,1,4)=" . $db2->escape($year) . "
                GROUP BY SUBSTRING(B.archived,1,4)";
        $db2->query($sql);
        $db2->nextRecord();

        $inThisLanguage = $db2->f(0);

        $icon = '<img alt="" src="' . $cfg['path']['images'] . 'folder.gif" class="vAlignMiddle">';

        // art
        $sql = "SELECT * FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('art') . " AS B, " . cRegistry::getDbTableName('art_lang') . " AS C WHERE A.idcat=" . $idcat . " AND A.idart=B.idart AND B.idart=C.idart
                AND C.idlang=" . $lang . " ORDER BY B.idart";
        $db2->query($sql);

        $numRows = $db2->numRows();

        if ($bCatVisible == 0) {
            $offOnline = '<img src="' . $cfg['path']['images'] . 'offline_off.gif" alt="' . i18n("Category is offline") . '" title="' . i18n("Category is offline") . '">';
        } else {
            $offOnline = '<img src="' . $cfg['path']['images'] . 'online_off.gif" alt="' . i18n("Category is online") . '" title="' . i18n("Category is online") . '">';
        }

        // check if there are subcategories
        $iSumSubCategories = 0;
        $sSql = "SELECT count(*) as cat_count from " . cRegistry::getDbTableName('cat') . " WHERE parentid=" . $idcat . ";";
        $db3 = cRegistry::getDb();
        $db3->query($sSql);
        if ($db3->nextRecord()) {
            $iSumSubCategories = $db3->f('cat_count');
        }
        $db3->free();

        $tpl->set('d', 'PADDING_LEFT', $paddingLeft);
        $tpl->set('d', 'TEXT', conHtmlSpecialChars($text) . ' (idcat: ' . cSecurity::toInteger($db->f('idcat')) . ')');
        $tpl->set('d', 'ICON', $icon);
        $tpl->set('d', 'STATUS', $offOnline);
        $tpl->set('d', 'NUMBEROFARTICLES', $numberOfArticles);
        $tpl->set('d', 'TOTAL', $total);
        $tpl->set('d', 'ROWNAME', implode('_', $aRowNames));
        $tpl->set('d', 'INTHISLANGUAGE', $inThisLanguage);

        if ($numRows > 0 || $iSumSubCategories > 0) {
            $tpl->set('d', 'EXPAND', '<a href="javascript:changeVisibility(\'' . implode('_', $aRowNames) . '\', ' . $db->f('level') . ', ' . $idcat . ')">
                                          <img src="' . $cfg['path']['images'] . 'open_all.gif"
                                               alt="' . i18n("Open category") . '"
                                               title="' . i18n("Open category") . '"
                                               id="' . implode('_', $aRowNames) . '_img"
                                               class="vAlignMiddle">
                                      </a>');
        } else {
            $tpl->set('d', 'EXPAND', '<img alt="" src="' . $cfg['path']['images'] . 'spacer.gif" width="7">');
        }

        if ($db->f('level') != 0) {
            $tpl->set('d', 'DISPLAY_ROW', 'none');
        } else {
            $tpl->set('d', 'DISPLAY_ROW', $sDisplay);
        }
        $frontendURL = cRegistry::getFrontendUrl();
        $catName = '';
        statCreateLocationString($db->f('idcat'), '&nbsp;/&nbsp;', $catName);
        $tpl->set('d', 'PATH', i18n("Path") . ':&nbsp;/&nbsp;' . $catName);
        $tpl->set('d', 'ULR_TO_PAGE', $frontendURL . 'front_content.php?idcat=' . $db->f('idcat'));

        $tpl->next();
        $currentRow++;

        while ($db2->nextRecord()) {
            $idart = cSecurity::toInteger($db2->f('idart'));

            $aRowNames[] = $idart;

            $numberOfArticles = '';

            $paddingLeft = 10 + (15 * ($db->f('level') + 1));

            $text = $db2->f('title');
            $online = $db2->f('online');

            // number of arts
            $db3 = cRegistry::getDb();

            // hits of art total
            $sql = "SELECT SUM(visited) FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat_archive') . " AS B WHERE A.idcatart=B.idcatart AND A.idcat=" . $idcat . "
                    AND A.idart=" . $idart . " AND B.idclient=" . $client . " AND SUBSTRING(B.archived,1,4)=" . $db3->escape($year) . "
                    GROUP BY SUBSTRING(B.archived,1,4)";
            $db3->query($sql);
            $db3->nextRecord();

            $total = $db3->f(0);

            // hits of art in this language
            $sql = "SELECT visited FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat_archive') . " AS B WHERE A.idcatart=B.idcatart AND A.idcat=" . $idcat . "
                    AND A.idart=" . $idart . " AND B.idlang=" . $lang . " AND B.idclient=" . $client . "
                    AND SUBSTRING(B.archived,1,4)=" . $db3->escape($year) . " GROUP BY SUBSTRING(B.archived,1,4)";
            $db3->query($sql);
            $db3->nextRecord();

            $inThisLanguage = $db3->f(0);

            if ($online == 0) {
                $offOnline = '<img src="' . $cfg['path']['images'] . 'offline_off.gif" alt="' . i18n("Article is offline") . '" title="' . i18n("Article is offline") . '">';
            } else {
                $offOnline = '<img src="' . $cfg['path']['images'] . 'online_off.gif" alt="' . i18n("Category is online") . '" title="' . i18n("Category is online") . '">';
            }

            $icon = '<img alt="" src="' . $cfg['path']['images'] . 'article.gif" class="vAlignMiddle">';
            $tpl->set('d', 'PADDING_LEFT', $paddingLeft);
            $tpl->set('d', 'TEXT', conHtmlSpecialChars($text) . ' (idart: ' . $idart . ')');
            $tpl->set('d', 'ICON', $icon);
            $tpl->set('d', 'STATUS', $offOnline);
            $tpl->set('d', 'ROWNAME', implode('_', $aRowNames));
            $tpl->set('d', 'NUMBEROFARTICLES', $numberOfArticles);
            $tpl->set('d', 'TOTAL', $total);
            $tpl->set('d', 'ROWNAME', implode('_', $aRowNames));
            $tpl->set('d', 'EXPAND', '<img alt="" src="' . $cfg['path']['images'] . 'spacer.gif" width="7">');
            $tpl->set('d', 'INTHISLANGUAGE', $inThisLanguage);
            $tpl->set('d', 'DISPLAY_ROW', 'none');
            $catName = '';
            statCreateLocationString($idart, '&nbsp;/&nbsp;', $catName);
            $tpl->set('d', 'PATH', i18n("Path") . ':&nbsp;/&nbsp;' . $catName);
            $tpl->set('d', 'ULR_TO_PAGE', $frontendURL . 'front_content.php?idart=' . $idart);

            $tpl->next();
            $currentRow++;

            array_pop($aRowNames);
        }
    }

    // hits total
    $sql = "SELECT SUM(visited) FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat_archive') . " AS B WHERE A.idcatart=B.idcatart AND B.idclient=" . $client . "
            AND SUBSTRING(B.archived,1,4)='" . $db->escape($year) . "' GROUP BY SUBSTRING(B.archived,1,4)";
    $db->query($sql);
    $db->nextRecord();

    $total = $db->f(0);

    // hits total on this language
    $sql = "SELECT SUM(visited) FROM " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat_archive') . " AS B WHERE A.idcatart=B.idcatart AND B.idlang=" . $lang . "
            AND B.idclient=" . $client . " AND SUBSTRING(B.archived,1,4)='" . $db->escape($year) . "' GROUP BY SUBSTRING(B.archived,1,4)";
    $db->query($sql);
    $db->nextRecord();

    $inThisLanguage = $db->f(0);

    $tpl->set('d', 'TEXT', '&nbsp;');
    $tpl->set('d', 'ICON', '');
    $tpl->set('d', 'STATUS', '');
    $tpl->set('d', 'PADDING_LEFT', '10');
    $tpl->set('d', 'NUMBEROFARTICLES', '');
    $tpl->set('d', 'TOTAL', '');
    $tpl->set('d', 'EXPAND', '');
    $tpl->set('d', 'ROWNAME', '');
    $tpl->set('d', 'INTHISLANGUAGE', '');
    $tpl->set('d', 'DISPLAY_ROW', $sDisplay);
    $tpl->set('s', 'SUMTEXT', i18n("Sum"));
    $tpl->set('s', 'SUMNUMBEROFARTICLES', $sumNumberOfArticles);
    $tpl->set('s', 'SUMTOTAL', $total);
    $tpl->set('s', 'SUMINTHISLANGUAGE', $inThisLanguage);
    $tpl->next();
}

/**
 * Generates a top<n> statistics page
 *
 * @param string $yearMonth
 *         Specifies the year and month from which to retrieve the statistics,
 *         specify "current" to retrieve the current entries.
 * @param int    $top
 *         Specifies the amount of pages to display
 *
 * @throws cDbException
 * @throws cException
 */
function statsOverviewTop($yearMonth, $top)
{
    global $tpl;

    $db = cRegistry::getDb();
    $client = cSecurity::toInteger(cRegistry::getClientId());
    $lang = cSecurity::toInteger(cRegistry::getLanguageId());

    if (strcmp($yearMonth, "current") == 0) {
        $sql = "SELECT DISTINCT
                    C.title, A.visited, C.idart
                FROM
                    " . cRegistry::getDbTableName('stat') . " AS A,
                    " . cRegistry::getDbTableName('cat_art') . " AS B,
                    " . cRegistry::getDbTableName('art_lang') . " AS C
                WHERE
                    C.idart = B.idart AND
                    C.idlang = A.idlang AND
                    B.idcatart = A.idcatart AND
                    A.idclient = " . $client . " AND
                    A.idlang = " . $lang . "
                ORDER BY A.visited DESC
                LIMIT " . $db->escape($top);
    } else {
        $sql = "SELECT DISTINCT
                    C.title, A.visited, B.idcat, C.idart
                FROM
                    " . cRegistry::getDbTableName('stat_archive') . " AS A,
                    " . cRegistry::getDbTableName('cat_art') . " AS B,
                    " . cRegistry::getDbTableName('art_lang') . " AS C
                WHERE
                    C.idart = B.idart AND
                    C.idlang = A.idlang AND
                    B.idcatart = A.idcatart AND
                    A.idclient = " . $client . " AND
                    A.archived = '" . $db->escape($yearMonth) . "' AND
                    A.idlang = " . $lang . " ORDER BY
                    A.visited DESC
                LIMIT " . $db->escape($top);
    }

    $db->query($sql);

    $frontendURL = cRegistry::getFrontendUrl();
    while ($db->nextRecord()) {
        $catName = '';
        statCreateLocationString($db->f(2), '&nbsp;/&nbsp;', $catName);
        $tpl->set('d', 'PADDING_LEFT', '5');
        $tpl->set('d', 'PATH', i18n("Path") . ':&nbsp;/&nbsp;' . $catName);
        $tpl->set('d', 'TEXT', conHtmlSpecialChars($db->f(0)) . ' (idart: ' . cSecurity::toInteger($db->f('idart')) . ')');
        $tpl->set('d', 'TOTAL', $db->f(1));
        $tpl->set('d', 'ULR_TO_PAGE', $frontendURL . 'front_content.php?idart=' . $db->f('idart'));
        $tpl->next();
    }
}

/**
 * Generates the location string for passed category id.
 *
 * Performs a recursive call, if parent category doesn't match to 0
 *
 * @param int    $idcat
 *         The category id
 * @param string $seperator
 *         Separator for location string
 * @param string $catStr
 *         The location string variable (reference)
 *
 * @throws cException
 * @throws cInvalidArgumentException
 */
function statCreateLocationString($idcat, $seperator, &$catStr)
{
    $cats = [];

    // get category path
    $helper = cCategoryHelper::getInstance();
    foreach ($helper->getCategoryPath($idcat) as $categoryLang) {
        $cats[] = $categoryLang->getField('name');
    }

    $catStr = implode($seperator, $cats);
}

/**
 * Generates a top<n> statistics page
 *
 * @param int $year
 *         Specifies the year from which to retrieve the statistics
 * @param int $top
 *         Specifies the amount of pages to display
 *
 * @throws cDbException
 * @throws cException
 * @throws cInvalidArgumentException
 */
function statsOverviewTopYear($year, $top)
{
    global $tpl;

    $db = cRegistry::getDb();
    $client = cSecurity::toInteger(cRegistry::getClientId());
    $lang = cSecurity::toInteger(cRegistry::getLanguageId());

    $sql = "SELECT
                C.title, SUM(A.visited) as visited, B.idcat AS idcat, C.idart AS idart
            FROM
                " . cRegistry::getDbTableName('stat_archive') . " AS A,
                " . cRegistry::getDbTableName('cat_art') . " AS B,
                " . cRegistry::getDbTableName('art_lang') . " AS C
            WHERE
                C.idart = B.idart AND
                C.idlang = A.idlang AND
                B.idcatart = A.idcatart AND
                A.idclient = " . $client . " AND
                A.archived LIKE '" . $db->escape($year) . "%' AND
                A.idlang = " . $lang . "
            GROUP BY A.idcatart
            ORDER BY visited DESC
            LIMIT " . $db->escape($top);

    $db->query($sql);
    $frontendURL = cRegistry::getFrontendUrl();
    while ($db->nextRecord()) {
        $catName = '';
        statCreateLocationString($db->f('idcat'), '&nbsp;/&nbsp;', $catName);

        $tpl->set('d', 'PADDING_LEFT', '5px');
        $tpl->set('d', 'PATH', i18n("Path") . ':&nbsp;/&nbsp;' . $catName);
        $tpl->set('d', 'TEXT', conHtmlSpecialChars($db->f(0)) . ' (idart: ' . cSecurity::toInteger($db->f('idart')) . ')');
        $tpl->set('d', 'TOTAL', $db->f(1));
        $tpl->set('d', 'ULR_TO_PAGE', $frontendURL . 'front_content.php?idart=' . $db->f('idart'));
        $tpl->next();
    }
}

/**
 * Returns a drop-down to choose the stats to display
 *
 * @param string $default
 *
 * @return string
 *         Returns a drop-down string
 *
 * @throws cException
 */
function statDisplayTopChooser($default)
{
    $defaultTop10 = ($default == 'top10') ? 'selected' : '';
    $defaultTop20 = ($default == 'top20') ? 'selected' : '';
    $defaultTop30 = ($default == 'top30') ? 'selected' : '';
    $defaultAll = ($default == 'all') ? 'selected' : '';

    return ("<form name=\"name\">" .
            "  <select class=\"text_medium\" onchange=\"top10Action(this)\">" .
            "    <option value=\"top10\" $defaultTop10>" . i18n("Top 10") . "</option>" .
            "    <option value=\"top20\" $defaultTop20>" . i18n("Top 20") . "</option>" .
            "    <option value=\"top30\" $defaultTop30>" . i18n("Top 30") . "</option>" .
            "    <option value=\"all\" $defaultAll>" . i18n("All") . "</option>" .
            "  </select>" .
            "</form>");
}

/**
 * Returns a drop-down to choose the stats to display for yearly summary pages
 *
 * @param string $default
 *
 * @return string
 *         Returns a drop-down string
 *
 * @throws cException
 */
function statDisplayYearlyTopChooser($default)
{
    $defaultTop10 = ($default == 'top10') ? 'selected' : '';
    $defaultTop20 = ($default == 'top20') ? 'selected' : '';
    $defaultTop30 = ($default == 'top30') ? 'selected' : '';
    $defaultAll = ($default == 'all') ? 'selected' : '';

    return ("<form name=\"name\">" .
            "  <select class=\"text_medium\" onchange=\"top10ActionYearly(this)\">" .
            "    <option value=\"top10\" $defaultTop10>" . i18n("Top 10") . "</option>" .
            "    <option value=\"top20\" $defaultTop20>" . i18n("Top 20") . "</option>" .
            "    <option value=\"top30\" $defaultTop30>" . i18n("Top 30") . "</option>" .
            "    <option value=\"all\" $defaultAll>" . i18n("All") . "</option>" .
            "  </select>" .
            "</form>");
}

/**
 * Return an array with all years which are available as stat files.
 *
 * @param int $client
 * @param int $lang
 *
 * @return array
 *         Array of strings with years.
 *
 * @throws cDbException|cInvalidArgumentException
 */
function statGetAvailableYears($client, $lang)
{
    $db = cRegistry::getDb();

    $sql = "SELECT
                SUBSTRING(`archived`, 1, 4)
            FROM
                `%s`
            WHERE
                idlang = %d AND
                idclient = %d
            GROUP BY
                SUBSTRING(`archived`, 1, 4)
            ORDER BY
                SUBSTRING(`archived`, 1, 4) DESC";
    $db->query($sql, cRegistry::getDbTableName('stat_archive'), $lang, $client);

    $availableYears = [];
    while ($db->nextRecord()) {
        $availableYears[] = $db->f(0);
    }

    return $availableYears;
}

/**
 * Return an array with all months for a specific year which are available
 * as stat files.
 *
 * @param string $year
 * @param int    $client
 * @param int    $lang
 *
 * @return array
 *         Array of strings with months.
 *
 * @throws cDbException|cInvalidArgumentException
 */
function statGetAvailableMonths($year, $client, $lang)
{
    $db = cRegistry::getDb();

    $availableYears = [];

    $sql = "SELECT
                SUBSTRING(`archived`, 5, 2)
            FROM
                `%s`
            WHERE
                `idlang` = %d AND
                `idclient` = %d AND
                SUBSTRING(`archived`, 1, 4) = '%s'
            GROUP BY
                SUBSTRING(`archived`, 5, 2)
            ORDER BY
                SUBSTRING(`archived`, 5, 2) DESC";

    $db->query($sql, cRegistry::getDbTableName('stat_archive'), $lang, $client, $year);
    while ($db->nextRecord()) {
        $availableYears[] = $db->f(0);
    }

    return $availableYears;
}

/**
 * Resets the statistic for passed client
 *
 * @param int $client
 *         Id of client
 *
 * @throws cDbException
 */
function statResetStatistic($client)
{
    $db = cRegistry::getDb();
    $sql = 'UPDATE `%s` SET `visited`= 0 WHERE `idclient` = %d';
    $db->query($sql, cRegistry::getDbTableName('stat'), $client);
}

/**
 * Deletes existing heap table (table in memory) and creates it.
 *
 * @param string $sHeapTable
 *         Table name
 * @param cDb    $db
 *         Database object
 *
 * @throws cDbException
 */
function buildHeapTable($sHeapTable, $db)
{
    $sql = "DROP TABLE IF EXISTS `" . $db->escape($sHeapTable) . "`;";
    $db->query($sql);

    $sql = "CREATE TABLE `" . $db->escape($sHeapTable) . "` TYPE=HEAP
                SELECT
                    A.idcatart,
                    A.idcat,
                    A.idart,
                    B.idstatarch,
                    B.archived,
                    B.idlang,
                    B.idclient,
                    B.visited
                FROM
                    " . cRegistry::getDbTableName('cat_art') . " AS A, " . cRegistry::getDbTableName('stat_archive') . " AS B
                WHERE
                    A.idcatart = B.idcatart;";
    $db->query($sql);

    $sql = "ALTER TABLE `" . $db->escape($sHeapTable) . "` ADD PRIMARY KEY (`idcatart`,`idcat` ,`idart`,`idstatarch` ,`archived`,`idlang`,`idclient` ,`visited`);";
    $db->query($sql);
}
