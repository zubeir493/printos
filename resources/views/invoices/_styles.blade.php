<style>
    body {
        font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
        color: #555;
        background: #F8F8F8;
        margin: 0;
        padding: 0;
    }
    .invoice-box {
        max-width: 800px;
        margin: 40px auto;
        border: 1px solid #eee;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
        font-size: 16px;
        line-height: 24px;
        background: #fff;
    }
    .invoice-box table {
        width: 100%;
        line-height: inherit;
        text-align: left;
        border-collapse: collapse;
    }
    .invoice-box table td {
        padding: 5px;
        vertical-align: top;
        border-bottom: 1px solid #eee;
    }
    .invoice-box table tr.top table td {
        padding-bottom: 20px;
    }
    .invoice-box table tr.top table td.title {
        font-size: 45px;
        line-height: 45px;
        color: #333;
    }
    .invoice-box table tr.information table td {
        padding-bottom: 40px;
    }
    .invoice-box table tr.heading td {
        background: #eee;
        border-bottom: 1px solid #ddd;
        font-weight: bold;
    }
    .invoice-box table tr.details td {
        padding-bottom: 20px;
    }
    .invoice-box table tr.item td {
        border-bottom: 1px solid #eee;
    }
    .invoice-box table tr.item.last td {
        border-bottom: none;
    }
    .invoice-box table tr.total td:nth-child(2) {
        border-top: 2px solid #eee;
        font-weight: bold;
    }
    .invoice-box .status-paid {
        background: #d4edda;
        color: #155724;
        padding: 5px 10px;
        border-radius: 4px;
        font-weight: bold;
    }
    .invoice-box .status-partial {
        background: #fff3cd;
        color: #856404;
        padding: 5px 10px;
        border-radius: 4px;
        font-weight: bold;
    }
    .invoice-box .status-unpaid {
        background: #f8d7da;
        color: #721c24;
        padding: 5px 10px;
        border-radius: 4px;
        font-weight: bold;
    }
    .invoice-box .paid-stamp {
        background: #d4edda;
        color: #155724;
        padding: 10px;
        border-radius: 4px;
        font-weight: bold;
        text-align: center;
        font-size: 18px;
        border: 2px solid #155724;
        width: 150px;
        margin: 20px auto;
    }
    .invoice-box .terms {
        font-size: 12px;
        color: #777;
        border-top: 1px solid #eee;
        padding-top: 10px;
        margin-top: 10px;
    }
    .terms-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        font-size: 11px;
        color: #777;
        border-top: 1px solid #eee;
        padding: 8px 40px;
        background: #fff;
    }
    .text-right { text-align: right; }
    @media only screen and (max-width: 600px) {
        .invoice-box {
            width: 100%;
            margin: 0;
            padding: 20px;
            box-shadow: none;
            border: 1px solid #ddd;
        }
    }
</style>
