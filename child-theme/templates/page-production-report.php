<?php
/**
 * Template Name: Production Report
 */
if (!defined('ABSPATH')) exit;

get_header();

function npm_dashboard_page_() {
    global $wpdb;
    if (!isset($_GET['start_date']) && !isset($_GET['end_date'])) {

        $latest_date = $wpdb->get_var("
            SELECT MAX(production_date)
            FROM {$wpdb->prefix}npm_production_entry
        ");

        $start_date = $latest_date ?: date('Y-m-d');
        $end_date   = $latest_date ?: date('Y-m-d');

    } else {

        $start_date = sanitize_text_field($_GET['start_date']);
        $end_date   = sanitize_text_field($_GET['end_date']);
    }



    $range_where = $wpdb->prepare("WHERE production_date BETWEEN %s AND %s", $start_date, $end_date);

    $totals = $wpdb->get_row("
        SELECT  
            SUM(target_quantity) as total_target,  
            SUM(produced_quantity) as total_produced,
            SUM(reject_quantity) as total_reject,
            SUM(other_reason_quantity) as total_other
        FROM {$wpdb->prefix}npm_production_entry
        $range_where
    ");

    $ach_pct = ($totals->total_target > 0) ? ($totals->total_produced / $totals->total_target) * 100 : 0;
    $rej_pct = ($totals->total_produced > 0) ? ($totals->total_reject / $totals->total_produced) * 100 : 0;
    
    // =========================
    // LAST 30 DAYS CHART DATA
    // =========================
    // 1. Get the raw data from DB
    $chart_rows = $wpdb->get_results("
        SELECT 
            production_date,
            SUM(target_quantity) as target,
            SUM(produced_quantity) as produced
        FROM {$wpdb->prefix}npm_production_entry
        WHERE production_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
        GROUP BY production_date
    ");

    // 2. Index the DB results by date for easy lookup
    $db_data = [];
    foreach ($chart_rows as $r) {
        $db_data[$r->production_date] = ($r->target > 0) ? round(($r->produced / $r->target) * 100, 2) : 0;
    }

    // 3. Generate EVERY day for the last 30 days
    $chart_labels = [];
    $chart_values = [];

    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $display_date = date('d M', strtotime($date));
        
        $chart_labels[] = $display_date;
        // If date exists in DB, use that value; otherwise, use 0
        $chart_values[] = isset($db_data[$date]) ? $db_data[$date] : 0;
    }

    // =========================
    // SECTION PERFORMANCE
    // =========================
    $sections_perf = $wpdb->get_results("
        SELECT s.id, s.section_name, s.section_in_charge,
               SUM(e.target_quantity) as target, 
               SUM(e.produced_quantity) as produced,
               SUM(e.reject_quantity) as reject,
               SUM(e.other_reason_quantity) as other,
               GROUP_CONCAT(DISTINCT e.production_title ORDER BY e.production_title SEPARATOR ', ') as section_titles
        FROM {$wpdb->prefix}npm_sections s
        LEFT JOIN {$wpdb->prefix}npm_production_entry e ON s.id = e.section_id
        $range_where
        GROUP BY s.id
    ");

    // =========================
    // BEST SECTIONS (Handles Ties)
    // =========================
    $best_sections = $wpdb->get_results("
        SELECT section_name, section_in_charge, perf FROM (
            SELECT s.section_name, s.section_in_charge, 
                   (SUM(e.produced_quantity)/SUM(e.target_quantity)*100) as perf
            FROM {$wpdb->prefix}npm_sections s
            JOIN {$wpdb->prefix}npm_production_entry e ON s.id = e.section_id
            $range_where
            GROUP BY s.id
            HAVING SUM(e.target_quantity) > 0
        ) as results 
        WHERE perf = (
            SELECT MAX(perf) FROM (
                SELECT (SUM(produced_quantity)/SUM(target_quantity)*100) as perf
                FROM {$wpdb->prefix}npm_production_entry
                $range_where
                GROUP BY section_id
                HAVING SUM(target_quantity) > 0
            ) as max_val
        )
    ");

    // =========================
    // BEST LINES (With Section Info & Handles Ties)
    // =========================
    $best_lines = $wpdb->get_results("
        SELECT line_name, section_name, perf FROM (
            SELECT 
                l.line_name, 
                s.section_name,
                (SUM(e.produced_quantity)/SUM(e.target_quantity)*100) as perf
            FROM {$wpdb->prefix}npm_lines l
            JOIN {$wpdb->prefix}npm_sections s ON l.section_id = s.id
            JOIN {$wpdb->prefix}npm_production_entry e ON l.id = e.line_id
            $range_where
            GROUP BY l.id
            HAVING SUM(e.target_quantity) > 0
        ) as results
        WHERE perf = (
            SELECT MAX(perf) FROM (
                SELECT (SUM(produced_quantity)/SUM(target_quantity)*100) as perf
                FROM {$wpdb->prefix}npm_production_entry
                $range_where
                GROUP BY line_id
                HAVING SUM(target_quantity) > 0
            ) as max_val
        )
    ");

    ?>
    <div class="npm-dashboard-wrapper">
        <div class="npm-header-flex" style="margin-bottom: 20px;">
            <h1 style="font-weight: 300;">Nemrac <span style="font-weight:bold;">Production Dashboard</span></h1>
            <div class="filters">
                <form method="get" id="npm-filter-form">
                    <div style="display:flex; flex-direction:column;">
                        <label style="font-size:10px; text-transform:uppercase; color:#888;">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>" style="border:1px solid #ddd; padding:5px;">
                    </div>
                    <div style="display:flex; flex-direction:column;">
                        <label style="font-size:10px; text-transform:uppercase; color:#888;">End Date</label>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>" style="border:1px solid #ddd; padding:5px;">
                    </div>
                    
                    <div style="display:flex; gap:5px; align-self: flex-end;">
                        <button type="submit" style="background: #0073aa; color: #fff; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer;">Filter</button>
                        
                        <a href="?start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d'); ?>"
                           style="background: #eee; color: #333; text-decoration: none; padding: 10px 15px; border-radius: 4px; font-size: 13px; border: 1px solid #ccc;">
                           Today
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="npm-stats-grid">
            <div class="npm-stat-card">
                <h3>Total Target</h3>
                <div class="value"><?php echo number_format($totals->total_target ?: 0); ?></div>
            </div>
            <div class="npm-stat-card">
                <h3>Total Produced</h3>
                <div class="value"><?php echo number_format($totals->total_produced ?: 0); ?></div>
            </div>
            <div class="npm-stat-card">
                <h3>Achievement %</h3>
                <div class="value"><?php echo number_format($ach_pct, 1); ?>%
                    <span class="trend" style="color:<?php echo $ach_pct >= 90 ? '#28a745' : ($ach_pct >= 85 ? '#ffc107' : '#dc3545'); ?>;">
                        <?php echo $ach_pct >= 85 ? '▲' : '▼'; ?>
                    </span>
                </div>
            </div>
            <div class="npm-stat-card">
                <h3>Rejection %</h3>
                <div class="value"><?php echo number_format($rej_pct, 1); ?>%
                    <span class="trend" style="color:<?php echo $rej_pct > 3 ? '#dc3545' : '#28a745'; ?>;">
                        <?php echo $rej_pct > 3 ? '▲' : '▼'; ?>
                    </span>
                </div>
            </div>

            </div>
                
        <?php
        $days = (strtotime($end_date) - strtotime($start_date)) / 86400 + 1;
        ?>
        <div style="color:#666; padding: 0 5px;">
            <strong>SHOWING REPORT FOR:</strong>
            <?php
            if ($start_date === $end_date) {
                echo date('d-m-Y', strtotime($start_date));
            } else {
                echo date('d-m-Y', strtotime($start_date)) . '<strong> to </strong>' . date('d-m-Y', strtotime($end_date)) . " <strong>({$days} days)</strong>";
            }
            ?>
        </div>
        <?php if (!$totals->total_target): ?>
            <div style="padding:15px; background:#fff3cd; border:1px solid #ffeeba; border-radius:6px; margin:15px 0;">
                No production data found for this period.
            </div>
        <?php else: ?>
        <div class="npm-main-container">
            <div class="npm-content-area">
                <div class="npm-card-table">
                    <table width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th align="left">Section Performance (Period)</th>
                                <th align="right">Target</th>
                                <th align="right">Produced</th>
                                <th align="right">Reject</th>
                                <th align="right">Other</th>
                                <th align="center">Ach %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($sections_perf as $row): 
                                $lines = $wpdb->get_results($wpdb->prepare("
                                    SELECT l.line_name, e.production_title, 
                                           SUM(e.target_quantity) as target, 
                                           SUM(e.produced_quantity) as produced, 
                                           SUM(e.reject_quantity) as reject, 
                                           SUM(e.other_reason_quantity) as other
                                    FROM {$wpdb->prefix}npm_lines l
                                    JOIN {$wpdb->prefix}npm_production_entry e ON l.id = e.line_id
                                    WHERE e.section_id = %d AND e.production_date BETWEEN %s AND %s
                                    GROUP BY l.id", $row->id, $start_date, $end_date));
                                
                                $has_lines = !empty($lines);
                                $pct = ($row->target > 0) ? ($row->produced / $row->target) * 100 : 0;
                                $class = ($pct >= 90) ? 'bg-high' : (($pct >= 85) ? 'bg-mid' : 'bg-low');
                            ?>
                            
                            <tr class="<?php echo $has_lines ? 'section-row' : ''; ?>" 
                                <?php echo $has_lines ? 'onclick="toggleSection('.$row->id.')"' : ''; ?> 
                                style="background: #f9f9f9; font-weight: bold; border-bottom: 1px solid #eee;">
                                <td align="left">
                                    <span class="toggle-icon" id="icon-<?php echo $row->id; ?>" style="display:inline-block; transition: transform 0.2s; width:15px;">
                                        <?php echo $has_lines ? '▶' : ''; ?>
                                    </span>
                                    <strong><?php echo esc_html($row->section_name); ?></strong>
                                    
                                    <?php 
                                    // Only show titles if it's a single day (days == 1) AND we aren't showing lines
                                    if ($days == 1 && !$has_lines && !empty($row->section_titles)): ?>
                                        <br>
                                        <small style="color: #0073aa; font-style: italic; margin-left: 25px;">
                                            <?php echo esc_html($row->section_titles); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td align="right"><?php echo number_format($row->target); ?></td>
                                <td align="right"><?php echo number_format($row->produced); ?></td>
                                <td align="right"><?php echo number_format($row->reject); ?></td>
                                <td align="right"><?php echo number_format($row->other); ?></td>
                                <td align="center">
                                    <span class="badge-pct <?php echo $class; ?>">
                                        <?php echo number_format($pct, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            
                            <?php if($has_lines): 
                                foreach($lines as $line): 
                                    $lpct = ($line->target > 0) ? ($line->produced / $line->target) * 100 : 0;
                                    $l_class = ($lpct >= 90) ? 'bg-high-text' : ($lpct >= 85 ? 'bg-mid-text' : 'bg-low-text');
                            ?>
                            <tr class="child-of-<?php echo $row->id; ?>" style="display: none; background: #ffffff;">
                                <td align="left" style="padding-left: 35px; border-bottom: 1px solid #f1f1f1;">
                                    <strong>↳ <?php echo esc_html($line->line_name); ?></strong><br>
                                    <small style="color: #0073aa; font-style: italic; margin-left: 15px;">
                                        <?php echo esc_html($line->production_title ?: 'No Title'); ?>
                                    </small>
                                </td>
                                <td align="right" style="vertical-align: middle;"><?php echo number_format($line->target); ?></td>
                                <td align="right" style="vertical-align: middle;"><?php echo number_format($line->produced); ?></td>
                                <td align="right" style="vertical-align: middle; color:#dc3545;"><?php echo number_format($line->reject); ?></td>
                                <td align="right" style="vertical-align: middle; color:#666;"><?php echo number_format($line->other); ?></td>
                                <td align="center" style="vertical-align: middle; font-weight:bold;" class="<?php echo $l_class; ?>">
                                    <?php echo number_format($lpct, 1); ?>%
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                            
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="npm-sidebar">
                <div class="npm-widget">
                    <h3>Period Best Section<?php echo count($best_sections) > 1 ? 's' : ''; ?></h3>
                    <?php if ($best_sections): foreach ($best_sections as $bs): ?>
                        <div class="npm-award-item" style="margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">
                            <span class="npm-award-icon">🏆</span>
                            <div>
                                <strong><?php echo esc_html($bs->section_name); ?></strong><br>
                                <?php if (!empty($bs->section_in_charge)): ?>
                                    <small><?php echo esc_html($bs->section_in_charge); ?></small><br>
                                <?php endif; ?>
                                <small style="font-weight: bold; color: #28a745;"><?php echo number_format($bs->perf, 1); ?>% Achievement</small>
                            </div>
                        </div>
                    <?php endforeach; else: echo "N/A"; endif; ?>
                </div>

                <div class="npm-widget">
                    <h3>Period Best Line<?php echo count($best_lines) > 1 ? 's' : ''; ?></h3>
                    <?php if ($best_lines): foreach ($best_lines as $bl): ?>
                        <div class="npm-award-item" style="margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">
                            <span class="npm-award-icon">🏅</span>
                            <div>
                                <strong><?php echo esc_html($bl->line_name); ?></strong> (<?php echo esc_html($bl->section_name); ?>)<br>
                                <small style="font-weight: bold; color: #28a745;">
                                    <?php echo number_format($bl->perf, 1); ?>% Achievement
                                </small>
                            </div>
                        </div>
                    <?php endforeach; else: ?>
                        <div style="font-size: 13px; color: #888;">No data available</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ================= LINE CHART ================= -->
    <div class="npm-chart-card">
        <h3>Production Achievement (Last 30 Days)</h3>
        <canvas id="achievementChart" height="120"></canvas>
    </div>
    
    <!-- ================= CHART.JS ================= -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
    const ctx = document.getElementById('achievementChart');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Achievement %',
                data: <?php echo json_encode($chart_values); ?>,
                borderWidth: 2,
                tension: 0.4, // Slightly reduced tension for better dot alignment
                fill: false,
                
                // --- DOT CONFIGURATION ---
                pointRadius: 4,               // Size of the dot
                pointHoverRadius: 6,          // Size when hovering
                pointBackgroundColor: '#0073aa', // Color of the dot
                pointBorderColor: '#fff',     // Border around the dot
                pointBorderWidth: 2,
                showLine: true                // Ensure the line still connects them
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Achievement: ' + context.parsed.y + '%';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 140,
                    ticks: {
                        callback: value => value + '%'
                    }
                }
            }
        }
    });
    </script>

    <style>
        .npm-chart-card {
            max-width: 900px;
            margin: 0 auto;
        }
        .npm-chart-card .npm-dashboard-wrapper {
            padding: 20px;
            background: #f4f7f9;
            font-family: sans-serif;
        }
        .npm-chart-card .npm-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        .npm-chart-card .npm-stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,.05);
        }
        .npm-chart-card .npm-stat-card h3 {
            font-size: 13px;
            text-transform: uppercase;
            color: #888;
        }
        .npm-chart-card .npm-stat-card .value {
            font-size: 24px;
            font-weight: bold;
            margin-top: 10px;
        }
        .npm-chart-card .npm-chart-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-top: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,.05);
        }
        .npm-chart-card .npm-chart-card h3 {
            font-size: 14px;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 15px;
        }
    </style>
    <script>
    function toggleSection(id) {
        const rows = document.querySelectorAll('.child-of-' + id);
        const icon = document.getElementById('icon-' + id);
        
        rows.forEach(row => {
            if (row.style.display === 'none') {
                row.style.display = 'table-row';
                if(icon) icon.style.transform = 'rotate(90deg)';
            } else {
                row.style.display = 'none';
                if(icon) icon.style.transform = 'rotate(0deg)';
            }
        });
    }
    </script>

    <style>
        .npm-dashboard-wrapper { font-family: sans-serif; padding: 20px; background: #f4f7f9; }
        .npm-stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .npm-stat-card h3 { margin: 0; font-size: 14px; color: #888; text-transform: uppercase; }
        .npm-stat-card .value { font-size: 24px; font-weight: bold; margin-top: 10px; }
        
        .npm-main-container { display: flex; gap: 20px; }
        .npm-content-area { flex: 3; }
        .npm-sidebar { flex: 1; display: flex; flex-direction: column; gap: 20px; }
        
        .npm-card-table { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .npm-card-table th { background: #f8f9fa; padding: 15px; border-bottom: 2px solid #eee; color: #666; font-size: 13px; }
        .npm-card-table td { padding: 12px 15px; border-bottom: 1px solid #f1f1f1; }
        
        .badge-pct { padding: 4px 10px; border-radius: 20px; color: #fff; font-size: 12px; font-weight: bold; display: inline-block; }
        .bg-high { background-color: #28a745; }
        .bg-mid  { background-color: #ffc107; color: #000; }
        .bg-low  { background-color: #dc3545; }
        .bg-high-text { color: #28a745; }
        .bg-mid-text  { color: #b98b00; }
        .bg-low-text  { color: #dc3545; }

        .npm-widget { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .npm-award-item { display: flex; align-items: center; gap: 15px; margin-top: 10px; }
        .npm-award-icon { font-size: 30px; }
        .section-row { cursor: pointer; }
        .section-row:hover { background: #f0f4f7 !important; }
        .npm-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        #npm-filter-form {display: flex; gap: 10px; align-items: center; background: #fff; padding: 10px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);}
    </style>
    <?php
}

npm_dashboard_page_();
get_footer();