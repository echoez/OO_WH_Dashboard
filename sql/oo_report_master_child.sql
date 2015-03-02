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
        ,to_char(s.imported_date, 'MM/DD/YY HH:MI:SS AM') AS Export_Time
        ,to_char(sgbs.date_created, 'MM/DD/YY HH:MI:SS AM') AS Pick_Start_Time
        ,to_char(sgss.picking_completed_date, 'MM/DD/YY HH:MI:SS AM') AS Pick_End_Time
        ,to_char(qal.log_date, 'MM/DD/YY HH:MI:SS AM') AS Gift_Box_Time
        ,to_char(s.shipped_date, 'MM/DD/YY HH:MI:SS AM') AS Proship_Time
        ,s.tracking_num AS tracking_number
        ,COUNT(ot.ticket_id) "# of ZD Tickets"
        ,MAX(ot.ticket_id) "Latest ZD Ticket"
 
      FROM
         shipment s
        ,address adr -- Find if Empl Zip Code
        ,return_sku rs -- If Overgood
        ,shipment_requeue sr 
        ,order_ticket ot -- Find Zendesk Ticket
        ,exporting_flags ef
        ,exporting_work_type ewt 
        ,qa_outbound_log qal --Find Gift Boxer
        ,daniel.sg_picker_batch_state sgbs -- Find Pick StartTime
        ,daniel.sg_shipment_state sgss -- Find Pick End Time
        ,order_shipment_gift osg -- to make the gift distinction
     
      WHERE 1 = 1
        AND s.is_cancelled = 0
        AND s.Authorized IS NOT NULL
        AND s.imported_date <= trunc(sysdate - 1) + 6/24
        AND (s.tracking_num is null OR s.settled is null)
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