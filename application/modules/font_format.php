<style>
    .ff-inp {
        width: 100%;
        height: 34px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 0 10px;
        font-family: inherit;
        font-size: 0.85rem;
        color: #0f172a;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
        outline: none;
    }
    .ff-inp:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }
    .ff-table {
        width: 100%;
        border-collapse: collapse;
    }
    .ff-table th, .ff-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
        vertical-align: middle;
        font-size: 0.9rem;
        color: #334155;
    }
    .ff-table th {
        background: #f8fafc;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.04em;
    }
    .ff-table tbody tr:last-child td {
        border-bottom: none;
    }
    .ff-row-label {
        font-weight: 600;
        color: #0f172a;
        white-space: nowrap;
    }
</style>

<div id="font-format-wrapper" style="background: #e2e8f0; padding: 1.5rem; display: flex; flex-direction: column; height: 100%; border-radius: 0;">
    
    <h3 style="text-align: center; font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em; font-family: 'SolaimanLipi', sans-serif;">
        Print Format Customize
    </h3>
    
    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; overflow-x: auto; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <table class="ff-table">
            <colgroup>
                <col style="width: 20%;">
                <col style="width: 40%;">
                <col style="width: 20%;">
                <col style="width: 20%;">
            </colgroup>
            <thead>
                <tr>
                    <th></th>
                    <th>Font Family</th>
                    <th>Font Size (pt)</th>
                    <th>Line Height (pt)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="ff-row-label">Left Side</td>
                    <td>
                        <select class="ff-inp">
                            <option>Times New Roman</option>
                            <option>Arial</option>
                            <option>Calibri</option>
                            <option>Tahoma</option>
                            <option>Georgia</option>
                        </select>
                    </td>
                    <td><input type="number" step="0.5" class="ff-inp" value="11"></td>
                    <td><input type="number" step="0.5" class="ff-inp" value="10"></td>
                </tr>
                <tr>
                    <td class="ff-row-label">Prescription</td>
                    <td>
                        <select class="ff-inp">
                            <option>Times New Roman</option>
                            <option>Arial</option>
                            <option>Calibri</option>
                            <option>Tahoma</option>
                            <option>Georgia</option>
                        </select>
                    </td>
                    <td><input type="number" step="0.5" class="ff-inp" value="11"></td>
                    <td><input type="number" step="0.5" class="ff-inp" value="11"></td>
                </tr>
                <tr>
                    <td class="ff-row-label">Line Gap</td>
                    <td></td>
                    <td></td>
                    <td><input type="number" step="0.5" class="ff-inp" value="5"></td>
                </tr>
                <tr>
                    <td class="ff-row-label" style="font-family: 'SolaimanLipi', sans-serif; font-size: 1rem;">ওষুধ বাংলা</td>
                    <td>
                        <select class="ff-inp">
                            <option>ZimRx Default</option>
                            <option>SolaimanLipi</option>
                            <option>Adorsho Lipi</option>
                            <option>Kongsho</option>
                        </select>
                    </td>
                    <td><input type="number" step="0.5" class="ff-inp" value="10.5"></td>
                    <td></td>
                </tr>
                <tr>
                    <td class="ff-row-label" style="font-family: 'SolaimanLipi', sans-serif; font-size: 1rem;">উপদেশঃ</td>
                    <td>
                        <select class="ff-inp">
                            <option>ZimRx Default</option>
                            <option>SolaimanLipi</option>
                            <option>Adorsho Lipi</option>
                            <option>Kongsho</option>
                        </select>
                    </td>
                    <td><input type="number" step="0.5" class="ff-inp" value="9.5"></td>
                    <td><input type="number" step="0.5" class="ff-inp" value="12"></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 15px; display: flex; justify-content: flex-end;">
        <button type="button" style="padding: 10px 24px; font-weight: 600; border-radius: 8px; border: none; background: #2563eb; color: #ffffff; font-size: 0.9rem; cursor: pointer; box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2); transition: background 0.2s;">
            Apply Formatting
        </button>
    </div>
</div>
