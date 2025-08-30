<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .receipt-container {
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid #ccc;
            padding: 20px;
        }
        .header, .footer {
            text-align: center;
            margin-bottom: 20px;
        }
        .items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items th, .items td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        .total {
            text-align: right;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <h1>Receipt</h1>
            <p>Date: {{ $receipt->date }}</p>
            <p>Receipt #: {{ $receipt->id }}</p>
        </div>
        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($receipt->lines as $line)
                <tr>
                    <td>{{ $line->product->name }}</td>
                    <td>{{ $line->qty }}</td>
                    <td>{{ $line->price }}</td>
                    <td>{{ $line->line_total }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="total">
            <p>Subtotal: {{ $receipt->subtotal }}</p>
            <p>Tax: {{ $receipt->tax_total }}</p>
            <p>Discount: {{ $receipt->discount_total }}</p>
            <p>Grand Total: {{ $receipt->grand_total }}</p>
        </div>
        <div class="footer">
            <p>Thank you for your purchase!</p>
             <p>Najib Shop - Jigjiga</p>
        </div>
    </div>
</body>
</html>