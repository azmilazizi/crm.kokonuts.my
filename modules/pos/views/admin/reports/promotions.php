<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('pos/admin/reports/_toolbar'); ?>

</div>
</div>

<script>
var _discChart = null, _promoChart = null;

function renderReport(r) {
    var el   = document.getElementById('report-content');
    var disc = r.discount_types || [];
    var pros = r.promotions     || [];
    var items = r.discounted_items || [];

    var totalDiscount = disc.reduce(function(a,b){ return a+parseFloat(b.total_discount||0); },0);
    var totalPromosUsed = pros.reduce(function(a,b){ return a+parseInt(b.receipts_used||0); },0);
    var totalPromoDiscount = pros.reduce(function(a,b){ return a+parseFloat(b.total_discount_given||0); },0);

    el.innerHTML = ''
        + '<div class="row">'
        + kpiCard('red',    'Total Discounts Given', 'RM ' + fmt2(totalDiscount))
        + kpiCard('orange', 'Active Promotions',      pros.length)
        + kpiCard('blue',   'Promo Receipts',         fmtInt(totalPromosUsed))
        + kpiCard('purple', 'Promo Discount Total',   'RM ' + fmt2(totalPromoDiscount))
        + '</div>'

        // Discount type donut + Promo performance bar
        + '<div class="row">'
        + '<div class="col-md-4"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Discount by Type</h5>'
        + '<canvas id="chart-disc" height="200"></canvas>'
        + '<div id="disc-legend" class="mtop10 small"></div>'
        + '<p id="disc-empty" class="text-muted text-center small" style="display:none;">No discounts in this period.</p>'
        + '</div></div></div>'
        + '<div class="col-md-8"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Promotion Performance</h5>'
        + '<canvas id="chart-promos" height="140"></canvas>'
        + '<p id="promo-empty" class="text-muted text-center small" style="display:none;">No promotions used in this period.</p>'
        + '</div></div></div>'
        + '</div>'

        // Promotions detail table
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Promotion Detail</h5>'
        + '<div style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin">'
        + '<thead><tr><th>#</th><th>Promotion</th><th>Type</th><th class="text-right">Receipts Used</th><th class="text-right">Items in Promo</th><th class="text-right">Discount Given</th></tr></thead>'
        + '<tbody id="promos-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>'

        // Most discounted items table
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Most Discounted Items</h5>'
        + '<div style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin">'
        + '<thead><tr><th>#</th><th>Product</th><th>Category</th><th class="text-right">Times Discounted</th><th class="text-right">Avg Discount/Line</th><th class="text-right">Total Discount</th></tr></thead>'
        + '<tbody id="disc-items-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>';

    var DISC_LABELS = { promotion: 'Promotion', manual: 'Manual', loyalty: 'Loyalty Redemption' };
    var DISC_COLORS = { promotion: '#f0ad4e', manual: '#d9534f', loyalty: '#9b59b6' };

    // Discount donut
    if (_discChart) _discChart.destroy();
    if (disc.length) {
        document.getElementById('disc-empty').style.display = 'none';
        var dLabels = disc.map(function(d){ return DISC_LABELS[d.discount_type] || d.discount_type; });
        var dData   = disc.map(function(d){ return parseFloat(d.total_discount||0); });
        var dColors = disc.map(function(d){ return DISC_COLORS[d.discount_type] || '#aaa'; });
        _discChart = new Chart(document.getElementById('chart-disc').getContext('2d'), {
            type: 'doughnut',
            data: { labels: dLabels, datasets: [{ data: dData, backgroundColor: dColors, borderWidth: 2 }]},
            options: { responsive: true, legend: { display: false }, cutoutPercentage: 55,
                tooltips: { callbacks: { label: function(ti,d){ return ' '+d.labels[ti.index]+': RM '+fmt2(d.datasets[0].data[ti.index]); } } }
            }
        });
        var dTotal = dData.reduce(function(a,b){return a+b;},0);
        document.getElementById('disc-legend').innerHTML = dLabels.map(function(l,i){
            var pct = dTotal > 0 ? (dData[i]/dTotal*100).toFixed(1) : 0;
            return '<span style="margin-right:12px;"><span style="display:inline-block;width:10px;height:10px;background:'+dColors[i]+';border-radius:2px;margin-right:4px;"></span>'
                + htmlEnc(l) + ' <strong>RM ' + fmt2(dData[i]) + '</strong> (' + pct + '%)</span>';
        }).join('');
    } else {
        document.getElementById('chart-disc').style.display = 'none';
        document.getElementById('disc-empty').style.display = '';
    }

    // Promo horizontal bar
    if (_promoChart) _promoChart.destroy();
    if (pros.length) {
        document.getElementById('promo-empty').style.display = 'none';
        _promoChart = new Chart(document.getElementById('chart-promos').getContext('2d'), {
            type: 'horizontalBar',
            data: {
                labels: pros.map(function(p){ return p.promotion_name; }),
                datasets: [{ label: 'Discount Given (RM)', data: pros.map(function(p){ return parseFloat(p.total_discount_given||0); }),
                    backgroundColor: 'rgba(240,173,78,0.75)', borderColor: '#f0ad4e', borderWidth: 1 }]
            },
            options: { responsive: true, legend: { display: false },
                scales: {
                    xAxes: [{ ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); } } }],
                    yAxes: [{ ticks: { fontSize: 11 }, gridLines: { display: false } }]
                }
            }
        });
    } else {
        document.getElementById('chart-promos').style.display = 'none';
        document.getElementById('promo-empty').style.display = '';
    }

    // Promos detail table
    document.getElementById('promos-tbody').innerHTML = pros.length ? pros.map(function(p, i) {
        return '<tr>'
            + '<td class="text-muted">' + (i+1) + '</td>'
            + '<td><strong>' + htmlEnc(p.promotion_name) + '</strong></td>'
            + '<td><span class="label label-default">' + htmlEnc(p.promotion_type) + '</span></td>'
            + '<td class="text-right">' + fmtInt(p.receipts_used) + '</td>'
            + '<td class="text-right">' + fmtInt(p.items_sold_in_promo) + '</td>'
            + '<td class="text-right text-warning"><strong>RM ' + fmt2(p.total_discount_given) + '</strong></td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="6" class="text-muted text-center">No promotions applied in this period.</td></tr>';

    // Most discounted items table
    document.getElementById('disc-items-tbody').innerHTML = items.length ? items.map(function(p, i) {
        return '<tr>'
            + '<td class="text-muted">' + (i+1) + '</td>'
            + '<td><strong>' + htmlEnc(p.item_name) + '</strong></td>'
            + '<td><small class="text-muted">' + htmlEnc(p.category_name) + '</small></td>'
            + '<td class="text-right">' + fmtInt(p.times_discounted) + '</td>'
            + '<td class="text-right">RM ' + fmt2(p.avg_discount_per_line) + '</td>'
            + '<td class="text-right text-danger"><strong>RM ' + fmt2(p.total_discount) + '</strong></td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="6" class="text-muted text-center">No discounted items in this period.</td></tr>';
}

function kpiCard(cls, label, value) {
    return '<div class="col-md-3"><div class="panel_s kpi-card ' + cls + '">'
        + '<div class="panel-body">'
        + '<div class="kpi-label">' + label + '</div>'
        + '<div class="kpi-value">' + value + '</div>'
        + '</div></div></div>';
}
</script>
<?php init_tail(); ?>
