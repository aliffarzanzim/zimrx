<?php
declare(strict_types=1);

// Font formatting module: font family, font size, and line height settings for prescription print.
?>
<div id="font-format-wrapper" style="background: var(--zrx-slate-200); padding: 1.5rem; display: flex; flex-direction: column; height: 100%; border-radius: 0;">
    
    <h3 style="text-align: center; font-size: 1.15rem; font-weight: 700; color: var(--zrx-text-main); margin-top: 0; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em; font-family: 'SolaimanLipi', sans-serif;">
        Print Format Customize
    </h3>
    
    <div style="background: var(--zrx-bg-card); border: 1px solid var(--zrx-border); border-radius: 8px; overflow-x: auto; box-shadow: var(--zrx-shadow-card);">
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
                            <option>Tinos</option>
                            <option>Arimo</option>
                            <option>Carlito</option>
                            <option>Noto Sans</option>
                            <option>Gelasio</option>
                        </select>
                    </td>
                    <td><input type="number" step="0.5" class="ff-inp" value="11"></td>
                    <td><input type="number" step="0.5" class="ff-inp" value="10"></td>
                </tr>
                <tr>
                    <td class="ff-row-label">Prescription</td>
                    <td>
                        <select class="ff-inp">
                            <option>Tinos</option>
                            <option>Arimo</option>
                            <option>Carlito</option>
                            <option>Noto Sans</option>
                            <option>Gelasio</option>
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
        <button type="button" style="padding: 10px 24px; font-weight: 600; border-radius: 8px; border: none; background: var(--zrx-primary); color: var(--zrx-white); font-size: 0.9rem; cursor: pointer; box-shadow: var(--zrx-shadow-md); transition: background 0.2s;">
            Apply Formatting
        </button>
    </div>
</div>
