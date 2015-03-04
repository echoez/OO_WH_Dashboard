<?php
//just testing
//$user = Oracle Connection variable
//$pass = Oracle Connection variable
//$host = MySQL Connection variable
//$conn = MySQL Connection variable
//$con = mysqli_connect(MYSQL Connection credentials);
 
$check_sql =
                SELECT
         s.order_id
         ,es.shipment_id AS shipment_ID
      ,case
           when UPPER(adr.postal_code) in ('%EMPL', 'EMPL%') then 'Employee'
           when s.is_hold = 1 then 'Hold'
           when s.IS_BACKORDER = 1 then 'Backorder'
           when s.tracking_num is NOT null and s.settled is null then 'Not Settled'
           when UPPER(rs.ship_method) in ('OVERGOOD', 'OVERGOODS') then 'Overgoods'
           when s.is_over_sold = 1 then 'Oversold'
           else 'Missing FF'
        end AS Status
        ,case
           when osg.shipment_id is null then ewt.work_type
           when osg.shipment_id is not null then 'Regular, Gift'
           when s.is_drop_ship != 1 then 'Drop Ship'
        end AS worktype
       
        -- FULFILLMENT_PROCESS_TIMES
        ,to_char(s.authorized, 'MM/DD/YY') AS Authorized_On
        ,to_char(es.date_exported, 'MM/DD/YY HH:MI:SS AM') AS Export_Time
        ,to_char(sgbs.date_created, 'MM/DD/YY HH:MI:SS AM') AS Pick_Start_Time
        ,to_char(sgss.picking_completed_date, 'MM/DD/YY HH:MI:SS AM') AS Pick_End_Time
        ,to_char(qal.log_date, 'MM/DD/YY HH:MI:SS AM') AS Gift_Box_Time
        ,to_char(s.shipped_date, 'MM/DD/YY HH:MI:SS AM') AS Proship_Time
        ,s.tracking_num AS tracking_number
        ,COUNT(ot.ticket_id) "# of ZD Tickets"
        ,MAX(ot.ticket_id) "Latest ZD Ticket"
 
      FROM
         shipment s
        ,address adr
        ,return_sku rs 
        ,shipment_requeue sr 
        ,order_ticket ot
        ,exporting_shipments es
        ,exporting_flags ef
        ,exporting_work_type ewt 
        ,qa_outbound_log qal
        ,daniel.sg_picker_batch_state sgbs 
        ,daniel.sg_shipment_state sgss 
        ,order_shipment_gift osg
     
      WHERE 1 = 1
        AND s.is_cancelled = 0
        AND s.Authorized IS NOT NULL
        AND s.imported_date <= trunc(sysdate - 1) + 6/24
        AND (s.tracking_num is null OR s.settled is null)
        AND s.shipment_id = es.shipment_id
        AND ot.order_id(+) = s.order_id
        AND s.shipment_id = adr.address_id(+)
        AND rs.shipment_id(+) = s.shipment_id
        AND sr.shipment_id(+) = s.shipment_id
        AND s.shipment_id = qal.shipment_id(+)
        AND s.shipment_id = osg.shipment_id(+)
        AND s.shipment_id = sgss.shipment_id(+)
        AND sgss.sg_picker_batch_state_id = sgbs.id(+)
        AND s.shipment_id = ef.shipment_id
        AND ewt.id = ef.work_type_id
     
      GROUP BY s.order_id
        ,es.shipment_id
        ,s.authorized
        ,es.date_exported
        ,sgbs.date_created
        ,sgss.picking_completed_date
        ,qal.log_date
        ,s.shipped_date
        ,s.tracking_num
        ,case
           when UPPER(adr.postal_code) in ('%EMPL', 'EMPL%') then 'Employee'
           when s.is_hold = 1 then 'Hold'
           when s.IS_BACKORDER = 1 then 'Backorder'
           when s.tracking_num is NOT null and s.settled is null then 'Not Settled'
          when UPPER(rs.ship_method) in ('OVERGOOD', 'OVERGOODS') then 'Overgoods'
           when s.is_over_sold = 1 then 'Oversold'
           else 'Missing FF'
        end
        ,case
           when osg.shipment_id is null then ewt.work_type
           when osg.shipment_id is not null then 'Regular, Gift'
           when s.is_drop_ship != 1 then 'Drop Ship'
         end
    ORDER BY es.date_exported desc
 
$check = oci_parse($conn, $check_sql);
 
oci_execute($check);
 
$message = "<head>
                                                <style>table, th, tr, td{border:1px solid black;white-space:nowrap;border-collapse:collapse}</style>
                                    </head>
                                    <body>";
$message .= "Good Morning, <br />Below you will find data for the outstanding orders exported from '2/13' through 3/1:<br /><br />";
$message .= "<table border = '1'>";
$message .= "<tr>
                                                <th>Order #</th>
                                                <th>Shipment ID</th>
                                                <th>Status</th>
                                                <th>Authorized</th>
                                                <th>Exported</th>
                                                <th>Pick Start</th>
                                                <th>Pick End</th>
                                                <th>Gift Time</th>
                                                <th>Proship Time</th>
                                                <th>Tracking</th>
                                                <th># of ZD Tickets</th>
                                                <th>Latest ZD Tickets</th>";
$message .= "</tr>";
 
while($checkz = oci_fetch_array($check))
            {
                        $order_id = $checkz[0];
                        $ship_id = $checkz[1];
                        $status = $checkz[2];
                        $authorized = $checkz[3];
                        $exported = $checkz[4];
                        $pick_start = $checkz[5];
                        $pick_end = $checkz[6];
                        $gift_time= $checkz[7];
                        $proship_time = $checkz[8];
                        $tracking = $checkz[9];
                        $zdTotal= $checkz[10];
                        $zdRecent = $checkz[11];

 
                        $mail_it = true;
           
                        if(empty($order_id))
                                    {
 
                                    }
                        else
                                    {
                                                $message .= "<tr>";
                                                $message .=               "<td> $order_id </td><td> $ship_id </td><td> $status</td><td> $authorized </td><td> $exported </td><td> $pick_start </td><td> $pick_end </td><td> $gift_time </td><td> $proship_time </td><td> $tracking </td><td> $zdTotal</td><td> $zdRecent</td>";
                                                $message .= "</tr>";
                                    }
                       
            }
 
if(empty($order_id))
            {
                        $mail_it = false;
            }
else
            {
                        $message .="  </table>
                                                         
                                                            </body>";
 
                        $to = "oogroup@uncommongoods.com";
                        $from = "donotreply@exportingalerts.info";
 
                        $subject = "Outstanding Order Alert";
 
                        $headers = "From:" . $from . "\r\n";
                        $headers .= 'MIME-Version: 1.0' . "\r\n";
                        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
            }          
 
 
if($mail_it==true)
            {
                        echo $message;
                        mail($to,$subject,$message,$headers);
            }
                                   
 
oci_free_statement($check);
 
?>
 