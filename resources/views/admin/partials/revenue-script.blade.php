<script>
(() => {
    const $ = id => document.getElementById(id);
    const dashboard = $('revenueDashboard');
    if (!dashboard) return;
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    const money = value => new Intl.NumberFormat('vi-VN', {maximumFractionDigits:2}).format(Number(value || 0)) + ' ₫';
    const integer = value => Number(value || 0).toLocaleString('vi-VN');
    const methods = {demo:'Thanh toán DEMO',cash:'Tiền mặt',card:'Thẻ',banking:'Chuyển khoản ngân hàng',bank_transfer:'Chuyển khoản ngân hàng',
        manual_banking:'Chuyển khoản ngân hàng',sepay:'Chuyển khoản SePay',vnpay:'VNPay',momo:'MoMo',zalopay:'ZaloPay',unknown:'Chưa rõ phương thức'};
    const methodName = value => methods[value] || 'Phương thức khác';
    const empty = message => '<div class="empty-state">' + escape(message) + '</div>';
    const params = new URLSearchParams(location.search);
    const allowedPeriods = ['today','7days','30days','month','year','custom'];
    let filters = {period:allowedPeriods.includes(params.get('period')) ? params.get('period') : 'today',
        payment_scope:params.get('payment_scope') === 'demo' ? 'demo' : 'real'};
    if (filters.period === 'custom') {
        filters.date_from = params.get('date_from') || '';filters.date_to = params.get('date_to') || '';
        $('reportDateFrom').value = filters.date_from;$('reportDateTo').value = filters.date_to;
        $('reportDateForm').hidden = false;$('reportCustomToggle').setAttribute('aria-expanded','true');
    }
    $('revenueScope').value = filters.payment_scope;
    let serial = 0, controller, page = 1, lastPage = 1, busy = false, lineChart, donutChart;

    function clearCharts() {
        for (const chart of [lineChart,donutChart]) {try {chart?.destroy();} catch {}}
        lineChart = null;donutChart = null;
        $('revenueLineChart').replaceChildren();$('paymentDonutChart').replaceChildren();
    }

    function message(text, error = false) {
        $('revenueStatus').textContent = text;$('revenueStatus').hidden = !text;$('revenueStatus').dataset.error = String(error);
    }

    function placeholders(text) {
        clearCharts();
        for (const id of ['kpi-total','kpi-monthly','kpi-tx','kpi-hotels']) $(id).textContent = '—';
        for (const id of ['revenueLineChart','topHotelsList','roomTypeList','upsellList','paymentMethodsList','monthSummaryGrid']) $(id).innerHTML = empty(text);
        $('transactionsTbody').innerHTML = '<tr><td colspan="6" class="empty-state">' + escape(text) + '</td></tr>';
        $('transactionsCount').textContent = text;$('transactionsPage').textContent = '';
        $('transactionsPrev').disabled = true;$('transactionsNext').disabled = true;
    }

    function markPeriod() {
        dashboard.querySelectorAll('[data-period]').forEach(button => {
            const active = button.dataset.period === filters.period;
            button.classList.toggle('active',active);button.setAttribute('aria-pressed',String(active));
        });
        $('reportCustomToggle').classList.toggle('active',filters.period === 'custom');
    }

    function renderChart(rows, version) {
        const target = $('revenueLineChart');target.replaceChildren();
        if (!rows.some(row => Number(row.total) > 0)) {target.innerHTML = empty('Chưa có doanh thu trong kỳ đã chọn.');return;}
        const fallback = () => {
            target.innerHTML = '<div class="report-series-data"><p>Chi tiết doanh thu theo thời gian</p><table><tbody>'
                + rows.map(row => '<tr><td>' + escape(row.label) + '</td><td>' + escape(money(row.total)) + '</td></tr>').join('') + '</tbody></table></div>';
        };
        if (typeof ApexCharts === 'undefined') {fallback();return;}
        try {
            const chart = new ApexCharts(target,{
                series:[{name:'Doanh thu',data:rows.map(row => Number(row.total))}],
                chart:{type:'area',height:224,fontFamily:'Be Vietnam Pro, sans-serif',toolbar:{show:false},animations:{enabled:false}},
                stroke:{curve:'straight',width:2.5},fill:{type:'gradient',gradient:{opacityFrom:.25,opacityTo:.02}},
                dataLabels:{enabled:false},colors:['#2563EB'],
                xaxis:{categories:rows.map(row => row.label),tickAmount:Math.min(rows.length,8),labels:{style:{fontSize:'11px'},hideOverlappingLabels:true}},
                yaxis:{min:0,labels:{formatter:value => new Intl.NumberFormat('vi-VN',{notation:'compact',maximumFractionDigits:1}).format(value)}},
                tooltip:{y:{formatter:value => money(value)}},grid:{borderColor:'#e2e8f0',strokeDashArray:4},
            });
            lineChart = chart;
            Promise.resolve(chart.render()).catch(() => {if(version === serial)fallback();});
        } catch {fallback();}
    }

    function renderHotels(rows) {
        const max = Math.max(0,...rows.map(row => Number(row.revenue)));
        $('topHotelsList').innerHTML = rows.length ? rows.map(row => '<div class="hotel-list-item"><div class="hotel-name">' + escape(row.name)
            + '</div><div class="bar-wrapper"><div class="bar-fill" style="width:' + (max ? Math.min(100,Number(row.revenue)/max*100) : 0)
            + '%"></div></div><div class="hotel-amount">' + escape(money(row.revenue)) + '</div></div>').join('') : empty('Chưa có khách sạn phát sinh doanh thu trong kỳ.');
    }

    function renderRoomTypes(rows) {
        $('roomTypeList').innerHTML = rows.length ? rows.map(row => '<div class="report-row"><span>' + escape(row.name)
            + '<small>' + escape(row.hotel_name) + ' · ' + integer(row.bookings) + ' đơn</small></span><strong>' + escape(money(row.total)) + '</strong></div>').join('')
            : empty('Chưa có tiền phòng từ các đơn đủ điều kiện trong kỳ.');
    }

    function renderServices(data) {
        if (!data.available) {$('upsellList').innerHTML = empty('Dự án hiện chưa có dữ liệu dịch vụ thêm.');return;}
        $('upsellList').innerHTML = data.items.length ? '<div class="report-row"><span>Tổng tiền dịch vụ trong đơn</span><strong>' + escape(money(data.total)) + '</strong></div>'
            + data.items.map(row => '<div class="report-row"><span>' + escape(row.name) + '</span><strong>' + escape(money(row.total)) + '</strong></div>').join('')
            + '<p class="report-note">Top 8 dịch vụ theo giá đã lưu, chưa phân bổ giảm giá và thuế. Phụ thu ngày/mùa đã tính trong tiền phòng.</p>'
            : empty('Chưa có dịch vụ thêm trong các đơn đủ điều kiện của kỳ này.');
    }

    function renderMethods(rows) {
        $('paymentMethodsList').innerHTML = rows.length ? rows.map(row => '<div class="report-row"><span>' + escape(methodName(row.method))
            + '<small>' + integer(row.count) + ' giao dịch</small></span><strong>' + escape(money(row.total)) + '</strong></div>').join('') : empty('Chưa có thanh toán đủ điều kiện trong kỳ.');
        if (!rows.length || typeof ApexCharts === 'undefined') return;
        try {
            const chart = new ApexCharts($('paymentDonutChart'),{chart:{type:'donut',height:190,animations:{enabled:false}},
                series:rows.map(row => Number(row.total)),labels:rows.map(row => methodName(row.method)),
                legend:{show:false},dataLabels:{enabled:false},colors:['#2563eb','#0d9488','#f59e0b','#8b5cf6','#64748b'],
                tooltip:{y:{formatter:value => money(value)}},plotOptions:{pie:{donut:{size:'68%'}}}});
            donutChart = chart;Promise.resolve(chart.render()).catch(()=>{});
        } catch {}
    }

    function renderTransactions(data) {
        page = data.current_page;lastPage = data.last_page;
        $('transactionsTbody').innerHTML = data.data.length ? data.data.map(row => {
            const [date,time] = String(row.paid_at || '').split(' ');
            return '<tr><td><strong>#' + Number(row.booking_id) + '</strong><small>' + escape(row.hotel_name) + ' · Giao dịch #' + Number(row.id)
                + '</small></td><td>' + escape(row.customer_name || 'Khách hàng đã lưu') + '</td><td><strong>' + escape(money(row.amount))
                + '</strong></td><td>' + escape(methodName(row.payment_method)) + '</td><td><span class="badge badge-paid">Đã thanh toán</span><small>'
                + (row.booking_status === 'completed' ? 'Đơn đã hoàn thành' : 'Đơn đã xác nhận') + '</small></td><td>' + escape(date?.split('-').reverse().join('/') || '—')
                + '<small>' + escape(time || '') + '</small></td></tr>';
        }).join('') : '<tr><td colspan="6" class="empty-state">Chưa có giao dịch đủ điều kiện trong kỳ đã chọn.</td></tr>';
        $('transactionsCount').textContent = data.total ? 'Giao dịch ' + (data.from || 0) + '–' + (data.to || 0) + ' / ' + integer(data.total) : '0 giao dịch trong kỳ';
        $('transactionsPage').textContent = 'Trang ' + page + ' / ' + lastPage;
        $('transactionsPrev').disabled = page <= 1;$('transactionsNext').disabled = page >= lastPage;
    }

    async function load(next = filters, requestedPage = 1) {
        filters = {...next};markPeriod();controller?.abort();controller = new AbortController();
        const activeController = controller, version = ++serial;
        busy = true;dashboard.setAttribute('aria-busy','true');$('revenueReload').disabled = true;
        placeholders('Đang tải dữ liệu…');message('');$('revenueRange').textContent = 'Đang tải báo cáo theo bộ lọc đã chọn…';
        let timedOut = false;const timeout = setTimeout(()=>{timedOut = true;activeController.abort();},30000);
        try {
            const query = new URLSearchParams({...filters,page:requestedPage});
            const response = await adminApi('/revenue?' + query,{signal:activeController.signal,cache:'no-store'});
            const data = await response.json().catch(()=>null);
            if (version !== serial) return;
            if (!response.ok) {
                if(response.status === 422 && data?.errors) {
                    const text = Object.values(data.errors).flat().join(' ');$('reportDateError').textContent = text;$('reportDateError').hidden = false;
                    if(filters.period === 'custom'){$('reportDateForm').hidden = false;$('reportCustomToggle').setAttribute('aria-expanded','true');}
                    throw new Error(text);
                }
                throw new Error(response.status === 401 || response.status === 403 ? 'Phiên Admin không còn hợp lệ. Vui lòng đăng nhập lại.' : 'Chưa tải được báo cáo. Bấm Cập nhật báo cáo để thử lại.');
            }
            if (!data?.period || !Array.isArray(data.series) || !Array.isArray(data.top_hotels) || !Array.isArray(data.transactions?.data)
                || !Array.isArray(data.room_types) || !Array.isArray(data.payment_methods) || !Array.isArray(data.monthly_revenue) || !Array.isArray(data.services?.items)) throw new Error('Dữ liệu báo cáo chưa đầy đủ. Vui lòng tải lại.');
            $('kpi-total').textContent = money(data.total_revenue);$('kpi-monthly').textContent = money(data.last_month_revenue);
            $('kpi-month-label').textContent = 'Doanh thu tháng ' + data.last_month_label;
            $('kpi-tx').textContent = integer(data.total_transactions);$('kpi-hotels').textContent = integer(data.hotel_count);
            $('revenueRange').textContent = data.period.scope_label + ' · ' + data.period.label;
            $('topHotelsPeriod').textContent = 'Top 5 trong kỳ ' + data.period.label;
            $('revenueChartUnit').textContent = 'VND · Theo ' + (data.group_by === 'day' ? 'ngày' : 'tháng');
            renderChart(data.series,version);renderHotels(data.top_hotels);renderRoomTypes(data.room_types);
            renderServices(data.services);renderMethods(data.payment_methods);renderTransactions(data.transactions);
            $('monthSummaryGrid').innerHTML = data.monthly_revenue.map(row => '<div class="month-card"><div class="month-name">' + escape(row.label)
                + '</div><div class="month-revenue">' + escape(money(row.total)) + '</div><div class="report-note">' + integer(row.count) + ' giao dịch</div></div>').join('');
            if (!$('reportDateFrom').value) $('reportDateFrom').value = data.period.date_from;
            if (!$('reportDateTo').value) $('reportDateTo').value = data.period.date_to;
            if (!data.total_transactions) message('Chưa có ' + (filters.payment_scope === 'demo' ? 'thanh toán DEMO' : 'thanh toán thực tế')
                + ' đủ điều kiện trong kỳ này. Hãy đổi khoảng ngày' + (filters.payment_scope === 'real' ? ' hoặc chọn Thanh toán DEMO để xem dữ liệu giả lập.' : '.'));
            else if (filters.payment_scope === 'demo') message('Đang xem thanh toán DEMO. Số liệu này được theo dõi riêng với doanh thu thanh toán thực tế.');
            const url = new URL(location.href);for(const key of ['period','payment_scope','date_from','date_to','page'])url.searchParams.delete(key);
            for(const [key,value]of Object.entries({...filters,page:data.transactions.current_page}))url.searchParams.set(key,value);
            history.replaceState(null,'',url);
        } catch (error) {
            if (version !== serial) return;
            placeholders('Chưa tải được dữ liệu.');$('revenueRange').textContent = 'Chưa tải được kỳ báo cáo đã chọn.';
            message(timedOut ? 'Tải báo cáo mất quá lâu. Bấm Cập nhật báo cáo để thử lại.' : error instanceof TypeError ? 'Không kết nối được máy chủ. Kiểm tra mạng rồi bấm Cập nhật báo cáo.' : error.message,true);
        } finally {
            clearTimeout(timeout);
            if (version === serial) {busy = false;dashboard.setAttribute('aria-busy','false');$('revenueReload').disabled = false;}
        }
    }

    dashboard.querySelectorAll('[data-period]').forEach(button=>button.addEventListener('click',()=>{
        $('reportDateForm').hidden = true;$('reportCustomToggle').setAttribute('aria-expanded','false');$('reportDateError').hidden = true;
        load({period:button.dataset.period,payment_scope:$('revenueScope').value});
    }));
    $('reportCustomToggle').addEventListener('click',()=>{
        const open = $('reportDateForm').hidden;$('reportDateForm').hidden = !open;$('reportCustomToggle').setAttribute('aria-expanded',String(open));if(open)$('reportDateFrom').focus();
    });
    $('reportDateForm').addEventListener('submit',event=>{
        event.preventDefault();
        const from = $('reportDateFrom'), to = $('reportDateTo');let error = '';
        from.setAttribute('aria-invalid','false');to.setAttribute('aria-invalid','false');
        if (!from.value || !from.checkValidity()) {error = 'Bạn chưa chọn ngày bắt đầu hợp lệ.';from.setAttribute('aria-invalid','true');}
        if (!to.value || !to.checkValidity()) {error += (error ? ' ' : '') + 'Bạn chưa chọn ngày kết thúc hợp lệ.';to.setAttribute('aria-invalid','true');}
        if (!error && to.value < from.value) {error = 'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.';to.setAttribute('aria-invalid','true');}
        if (!error && (Date.parse(to.value+'T00:00:00Z')-Date.parse(from.value+'T00:00:00Z'))/86400000 > 365) {error = 'Mỗi báo cáo tối đa 366 ngày.';to.setAttribute('aria-invalid','true');}
        $('reportDateError').textContent = error;$('reportDateError').hidden = !error;
        if(error){(from.getAttribute('aria-invalid') === 'true' ? from : to).focus();return;}
        load({period:'custom',date_from:from.value,date_to:to.value,payment_scope:$('revenueScope').value});
    });
    $('revenueScope').addEventListener('change',()=>load({...filters,payment_scope:$('revenueScope').value}));
    $('revenueReload').addEventListener('click',()=>load(filters,page));
    $('transactionsPrev').addEventListener('click',()=>{if(!busy&&page>1)load(filters,page-1);});
    $('transactionsNext').addEventListener('click',()=>{if(!busy&&page<lastPage)load(filters,page+1);});
    const initialPage = Number(params.get('page'));
    load(filters,Number.isSafeInteger(initialPage)&&initialPage>0?initialPage:1);
})();
</script>
