<style>
    @page {
        margin: 28px;
    }

    body {
        background: #ffffff;
        color: #222222;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 12px;
        line-height: 1.45;
        margin: 0;
        padding: 0;
    }

    .invoice-box {
        background: #ffffff;
        border: 0;
        margin: 0 auto 34px;
        max-width: 800px;
        padding: 26px;
    }

    .invoice-box table {
        border-collapse: collapse;
        text-align: left;
        width: 100%;
    }

    .invoice-box table td {
        border-bottom: 1px solid #e6e8ec;
        padding: 7px 8px;
        vertical-align: top;
    }

    .invoice-box table tr.top table td {
        border-bottom: 0;
        padding: 0;
    }

    .invoice-box table tr.top table td.title {
        color: #1f2933;
        font-size: 24px;
        line-height: 1.2;
    }

    .invoice-box table tr.information table td {
        border-bottom: 0;
        padding: 18px 0 20px;
    }

    .invoice-box table tr.heading td {
        background: #1f2933;
        border-bottom: 1px solid #1f2933;
        color: #ffffff;
        font-size: 11px;
        font-weight: bold;
        letter-spacing: .02em;
    }

    .invoice-box table tr.details td {
        padding-bottom: 20px;
    }

    .invoice-box table tr.item td {
        border-bottom: 1px solid #e6e8ec;
    }

    .invoice-box table tr.item.last td {
        border-bottom: none;
    }

    .invoice-box .status-paid,
    .invoice-box .status-partial,
    .invoice-box .status-unpaid {
        font-weight: bold;
        padding: 4px 8px;
    }

    .invoice-box .status-paid {
        background: #e7f4ec;
        color: #17633a;
    }

    .invoice-box .status-partial {
        background: #fff4d6;
        color: #7a5200;
    }

    .invoice-box .status-unpaid {
        background: #fbe4e7;
        color: #8d1f2d;
    }

    .invoice-box .paid-stamp {
        border: 2px solid #17633a;
        color: #17633a;
        font-size: 16px;
        font-weight: bold;
        margin: 18px auto 0;
        padding: 8px 12px;
        text-align: center;
        width: 150px;
    }

    .invoice-box .terms {
        border-top: 1px solid #d9dde3;
        color: #4b5563;
        font-size: 11px;
        margin-top: 12px;
        padding-top: 10px;
    }

    .terms-footer {
        background: #ffffff;
        border-top: 1px solid #d9dde3;
        bottom: 0;
        color: #4b5563;
        font-size: 10px;
        left: 0;
        padding: 8px 28px;
        position: fixed;
        right: 0;
    }

    .document-heading {
        border-bottom: 2px solid #1f2933;
        margin-bottom: 18px;
        padding-bottom: 14px;
    }

    .document-logo {
        display: block;
        margin-bottom: 9px;
        max-height: 58px;
        max-width: 132px;
        object-fit: contain;
    }

    .document-title {
        color: #1f2933;
        font-size: 24px;
        font-weight: bold;
        line-height: 1.2;
        margin: 0;
    }

    .document-subtitle {
        color: #5f6b7a;
        font-size: 11px;
        margin-top: 4px;
    }

    .document-meta {
        color: #384250;
        font-size: 11px;
        line-height: 1.55;
        text-align: right;
    }

    .party-block {
        background: #f7f8fa;
        border: 1px solid #e1e4e8;
        padding: 12px;
    }

    .party-label {
        color: #5f6b7a;
        display: block;
        font-size: 10px;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .summary-table {
        float: right;
        margin: 14px 0 0 auto;
        width: 310px;
    }

    .summary-table td {
        border-bottom: 1px dashed #cfd5dd;
        padding: 8px 0;
    }

    .summary-table tr:last-child td {
        border-bottom: 0;
        border-top: 1px solid #1f2933;
        font-size: 13px;
        font-weight: bold;
        padding-top: 10px;
    }

    .text-right {
        text-align: right;
    }

    @media only screen and (max-width: 600px) {
        .invoice-box {
            border: 0;
            margin: 0;
            padding: 20px;
            width: 100%;
        }
    }
</style>
