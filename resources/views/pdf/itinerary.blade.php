<!DOCTYPE html>
<html lang="vi"><head><meta charset="utf-8"><title>{{ $trip['title'] }}</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#142b49;line-height:1.6}h1{font-size:21px}h2{font-size:15px;border-bottom:1px solid #ddd;padding-bottom:7px;margin-top:25px}table{width:100%;border-collapse:collapse}td{vertical-align:top;padding:10px 6px;border-bottom:1px solid #eee}.time{width:95px}p{margin:5px 0}.muted{color:#667085}tr{page-break-inside:avoid}</style>
</head><body><p>Angel · LỊCH TRÌNH CỦA BẠN</p><h1>{{ $trip['title'] }}</h1>
<p>{{ $trip['location']['name'] }} · {{ $trip['total_days'] }} ngày · {{ $trip['party']['total'] }} người</p>
<p>Ngân sách dự kiến: {{ $trip['budget']['limit'] === null ? 'Chưa xác định' : number_format($trip['budget']['limit'], 0, ',', '.').' VND' }}</p>
<p>Nhu cầu lưu trú: {{ ['aurel_hotel'=>'Cần chỗ ở', 'own_accommodation'=>'Đã có chỗ ở', 'day_trip'=>'Đi trong ngày'][$trip['stay_mode']] ?? '' }}</p>
<p class="muted">Thời gian và chi phí là dự kiến. Kiểm tra giờ mở cửa, giá và điều kiện thực tế trước chuyến đi.</p>
@if(!empty($trip['planning_area']['notice']))<p>{{ $trip['planning_area']['notice'] }}</p>@endif
@foreach($trip['planning_warnings'] ?? [] as $warning)<p class="muted">{{ $warning }}</p>@endforeach
@foreach($trip['days'] as $day)
<h2>Ngày {{ $loop->iteration }}@if(!$trip['provisional']) · {{ $day['date'] }}@endif</h2>
<table>
@forelse($day['items'] as $item)
<tr><td class="time">{{ $item['start_time'] ?: 'Chưa đặt giờ' }}
@if($item['end_time']) – {{ $item['end_time'] }} @endif
</td><td><strong>{{ $item['title'] }}</strong>
@if($item['place_name'])
<p>{{ $item['place_name'] }}</p>
@endif
@if($item['description'])
<p>{{ $item['description'] }}</p>
@endif
@if(!empty($item['opening_check']))
<p class="muted">{{ $item['opening_check']['label'] }} · Giờ tham khảo</p>
@if(in_array($item['opening_check']['status'], ['closed','outside_hours','place_disabled']))<p>{{ $item['opening_check']['message'] ?? 'Bạn nên kiểm tra lại và cân nhắc đổi giờ hoặc địa điểm.' }}</p>@endif
@endif
@if(($amount = $item['estimated_cost'] ?? $item['ticket_estimate']['amount'] ?? null) !== null)
<p class="muted">Chi phí dự kiến: {{ number_format($amount, 0, ',', '.').' VND' }}</p>
@endif
</td></tr>
@empty
<tr><td>Chưa có hoạt động.</td></tr>
@endforelse
</table>
@endforeach</body></html>
