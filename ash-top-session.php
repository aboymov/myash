<?
   $connect = oci_connect($_POST["username"], $_POST["password"], $_POST["host"].':'. $_POST["port"] . '/' . $_POST["service"]);
   
   if ($connect) {
      if (isset($_POST["waitclass"])) {

         if ($_POST["waitclass"] == 'CPU') {
               $c = 'is null';
            } else {
               $c = "= '" . $_POST["waitclass"] . "'";
         }

         $query = "select count(*) activity
                     from V\$ACTIVE_SESSION_HISTORY
                    where sample_time > to_date('" . $_POST["startdate"] ."', 'DD.MM.YYYY HH24:MI:SS')
                      and sample_time < to_date('" . $_POST["enddate"] ."', 'DD.MM.YYYY HH24:MI:SS')
                      and wait_class " . $c . "
                      ";
      } else {
         $query = "select count(*) activity
                     from V\$ACTIVE_SESSION_HISTORY
                    where sample_time > to_date('" . $_POST["startdate"] ."', 'DD.MM.YYYY HH24:MI:SS')
                      and sample_time < to_date('" . $_POST["enddate"] ."', 'DD.MM.YYYY HH24:MI:SS')
                      ";
      }

      $statement = oci_parse($connect, $query);
      oci_execute($statement);
      $nrows = oci_fetch_all($statement, $results);

      $sum_activity=$results["ACTIVITY"][0];

      if (isset($_POST["waitclass"])) {

         if ($_POST["waitclass"] == 'CPU') {
            $c = 'is null';
         } else {
            $c = "= '" . $_POST["waitclass"] . "'";
         }
         $query = "select h.*, u.username from (
                     select h1.session_id || ',' ||  h1.session_serial# session_id, h2.program, nvl(h2.event,'CPU') wait_class, user_id, round(count(*)/" . $sum_activity ."*100,2) percent, n from (
                       select * from (
                           select session_id, session_serial#, count(*) n from v\$active_session_history
                            where sample_time > to_date('" . $_POST["startdate"] ."', 'DD.MM.YYYY HH24:MI:SS')
                              and sample_time < to_date('" . $_POST["enddate"] ."', 'DD.MM.YYYY HH24:MI:SS')
                              and wait_class " . $c . "
                            group by session_id, session_serial#
                            order by 3 desc
                        )  where rownum <= 10 ) h1, v\$active_session_history h2
                        where h1.session_id = h2.session_id
                          and h1.session_serial# = h2.session_serial#
                          and sample_time > to_date('" . $_POST["startdate"] ."', 'DD.MM.YYYY HH24:MI:SS')
                          and sample_time < to_date('" . $_POST["enddate"] ."', 'DD.MM.YYYY HH24:MI:SS')
                          and wait_class " . $c . "
                        group by h1.session_id, h1.session_serial#, h2.program, nvl(h2.event,'CPU'), n, user_id) h, dba_users u
                     where u.user_id = h.user_id
                     order by n desc, session_id desc";
        } else {
         $query = "select h.*, u.username from (
                     select h1.session_id || ',' ||  h1.session_serial# session_id, h2.program, nvl(h2.wait_class,'CPU') wait_class, user_id, round(count(*)/" . $sum_activity ."*100,2) percent, n from (
                       select * from (
                           select session_id, session_serial#, count(*) n from v\$active_session_history
                            where sample_time > to_date('" . $_POST["startdate"] ."', 'DD.MM.YYYY HH24:MI:SS')
                              and sample_time < to_date('" . $_POST["enddate"] ."', 'DD.MM.YYYY HH24:MI:SS')
                            group by session_id, session_serial#
                            order by 3 desc
                        )  where rownum <= 10 ) h1, v\$active_session_history h2
                        where h1.session_id = h2.session_id
                          and h1.session_serial# = h2.session_serial#
                          and sample_time > to_date('" . $_POST["startdate"] ."', 'DD.MM.YYYY HH24:MI:SS')
                          and sample_time < to_date('" . $_POST["enddate"] ."', 'DD.MM.YYYY HH24:MI:SS')
                        group by h1.session_id, h1.session_serial#, h2.program, nvl(h2.wait_class,'CPU'), n, user_id) h, dba_users u
                     where u.user_id = h.user_id
                     order by n desc, session_id desc";
      }

     /*
      print "<pre>";
      print $query . "<br>";
      print "</pre>";
      */
      $start_time = microtime(true);
      $statement = oci_parse($connect, $query);
      oci_execute($statement);
      $nrows = oci_fetch_all($statement, $results);

      $top = array();
      for ($i=0; $i<sizeof($results["N"]); $i++) {
         if (!isset($top[$results["SESSION_ID"][$i]]["PROGRAM"])) {
            $top[$results["SESSION_ID"][$i]]["PROGRAM"] = $results["PROGRAM"][$i];
         }
         if (!isset($top[$results["SESSION_ID"][$i]]["SESSION_ID"])) {
            $top[$results["SESSION_ID"][$i]]["SESSION_ID"] = $results["SESSION_ID"][$i];
         }
        /* if (!isset($top[$results["SESSION_ID"][$i]]["SQL_OPCODE"])) {
            $top[$results["SESSION_ID"][$i]]["SQL_OPCODE"] = $results["SQL_OPCODE"][$i];
         }*/
         if (!isset($top[$results["SESSION_ID"][$i]]["USERNAME"])) {
            $top[$results["SESSION_ID"][$i]]["USERNAME"] = $results["USERNAME"][$i];
         }
         if (!isset($top[$results["SESSION_ID"][$i]]["PERCENT_TOTAL"])) {
            $top[$results["SESSION_ID"][$i]]["PERCENT_TOTAL"] = $results["N"][$i]/$sum_activity*100;
         }

         $top[$results["SESSION_ID"][$i]]["WAIT_CLASS"][$results["WAIT_CLASS"][$i]] = $results["PERCENT"][$i];
      }

      /*
      print "<pre>";
      print_r($top);
      print "</pre>";
      */
      print "<table  class='output'>";
      print "<thead>";
      print "<tr><th align='left' nowrap>SID,Serial#&nbsp;&nbsp;</th>";
      print "<th width='150px' align='left'>Activity</th>";
      print "<th align='left'>Username&nbsp;&nbsp;</th>";
      print "<th align='left'>Program</th></tr>";
      print "</thead>";

      foreach ($top as $session) {
         print "<tr>";
         print "<td>".$session["SESSION_ID"] . "</td>";
         print "<td>";

         print "<table width=100%><tr>";
         print "<td width=100%'>";
         foreach ($session["WAIT_CLASS"] as $key => $value) {
            $bg = $_POST["eventColors"][$key];
            print "<div style='background:$bg;width:$value%;float:left;'>&nbsp;</div>";
         }
         print "</td><td>";
         print "<div style=''>".number_format(round($session["PERCENT_TOTAL"],2),2,'.',''). "%</div></td>";
         print "</td>";
         print "</tr></table>";

         print "<td nowrap>". $session["USERNAME"] . "</td>";
         print "<td nowrap>". $session["PROGRAM"] . "</td>";
         print "</tr>";
      }
      $end_time = microtime(true);
      print "</table>";
      print "<div align='right'><font style='font-family: Tahoma,Verdana,Helvetica,sans-serif;font-size:9px' color='gray'>Total Sample Count: $sum_activity, Returned in: ".round($end_time - $start_time,2) . "s</font></div>";
   }
?>