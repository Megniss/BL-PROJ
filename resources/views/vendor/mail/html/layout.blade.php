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

/* telefonā sadaļas pa visu platumu viena zem otras */
/* bez media query tās arī pāriet uz leju, tikai paliek 284px platas */
@media only screen and (max-width: 620px) {
.col {
display: block !important;
max-width: 100% !important;
}

.col-pad {
padding: 0 !important;
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

p, td, li, .book-meta, .stat-label, .panel-content, .panel-content p {
color: #e5ddd0 !important;
}

h1, h2, .book-title {
color: #faf6ef !important;
}

a, .header a, .book-badge, .stat-value {
color: #8fc493 !important;
}

.book-rank {
color: #e0a256 !important;
}

.stat {
background-color: #2d3a2e !important;
}

.book-row td {
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
