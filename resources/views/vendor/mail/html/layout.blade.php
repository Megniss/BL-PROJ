<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
{{-- klients drīkst rādīt tumšo režīmu, krāsas zemāk --}}
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<style>
@media only screen and (max-width: 680px) {
.inner-body {
width: 100% !important;
}

.footer {
width: 100% !important;
}

.content-cell {
padding: 20px !important;
}
}

/* telefonā kartītes pa visu platumu viena zem otras, vāks pa kreisi, teksts pa labi */
/* bez media query tās arī pāriet uz leju, tikai paliek 190px platas un centrā */
@media only screen and (max-width: 620px) {
.card {
display: block !important;
max-width: 100% !important;
}

.card-pad {
padding: 12px 0 !important;
border-bottom: 1px solid #e5ddd0 !important;
overflow: hidden !important;
}

.card-cover {
float: left !important;
margin-right: 14px !important;
}

.card-body {
padding-top: 4px !important;
overflow: hidden !important;
}

.card-body p {
text-align: left !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
text-align: center !important;
}

.stat-value {
font-size: 22px !important;
}
}

/* dark mode - apple mail, ios, outlook.com. gmail šo ignorē un pats apgriež krāsas */
@media (prefers-color-scheme: dark) {
body, .wrapper, .body {
background-color: #1a1612 !important;
}

.inner-body {
background-color: #262019 !important;
border-color: #3d3630 !important;
}

p, td, li, .card-meta, .stat-label, .panel-content, .panel-content p {
color: #e5ddd0 !important;
}

h1, h2, .card-title {
color: #faf6ef !important;
}

a, .header a, .card-badge, .stat-value {
color: #8fc493 !important;
}

.card-rank {
color: #e0a256 !important;
}

.stat, .card-ph {
background-color: #2d3a2e !important;
}

.card-pad {
border-color: #3d3630 !important;
}

.panel-content {
background-color: #2d3a2e !important;
}

.button {
color: #ffffff !important;
}

.footer p, .footer a {
color: #b0a090 !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="640" cellpadding="0" cellspacing="0" role="presentation">
<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
