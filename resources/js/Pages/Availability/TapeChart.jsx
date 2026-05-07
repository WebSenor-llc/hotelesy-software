import React, { useState, useEffect, useMemo } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

/**
 * TapeChart - the "tape" view used at every hotel front desk.
 *
 * Shows: room types (rows) × dates (columns), with cell colour indicating
 * availability:
 *   green    = plenty available
 *   yellow   = low availability
 *   red      = sold out / stop-sell
 *
 * Click a cell to start a new booking with those parameters pre-filled.
 *
 * Production version should add:
 *   - Drag-to-select date range
 *   - Per-room (not per-type) detail mode for room assignment
 *   - Inline rate editing (manager role only)
 *   - Channel manager sync indicator per cell
 */
export default function TapeChart({ propertyId = 1 }) {
    const [from, setFrom] = useState(() => new Date().toISOString().slice(0, 10));
    const [to, setTo] = useState(() => {
        const d = new Date();
        d.setDate(d.getDate() + 13);
        return d.toISOString().slice(0, 10);
    });
    const [matrix, setMatrix] = useState({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const dates = useMemo(() => {
        const out = [];
        const start = new Date(from);
        const end = new Date(to);
        for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
            out.push(d.toISOString().slice(0, 10));
        }
        return out;
    }, [from, to]);

    const roomTypeIds = useMemo(() => {
        const ids = new Set();
        Object.values(matrix).forEach((day) => {
            Object.keys(day).forEach((id) => ids.add(id));
        });
        return Array.from(ids);
    }, [matrix]);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError(null);
        axios
            .get(`/api/properties/${propertyId}/availability`, { params: { from, to } })
            .then((res) => {
                if (!cancelled) setMatrix(res.data.data || {});
            })
            .catch((e) => {
                if (!cancelled) setError(e.response?.data?.message || 'Failed to load availability.');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });
        return () => {
            cancelled = true;
        };
    }, [propertyId, from, to]);

    const cellClass = (cell) => {
        if (!cell) return 'bg-slate-100 text-slate-400';
        if (cell.stop_sell) return 'bg-red-700 text-white font-semibold';
        if (cell.available === 0) return 'bg-red-500 text-white font-semibold';
        const pct = cell.total > 0 ? cell.available / cell.total : 1;
        if (pct < 0.2) return 'bg-amber-400 text-slate-900';
        if (pct < 0.5) return 'bg-amber-200 text-slate-800';
        return 'bg-emerald-100 text-emerald-900';
    };

    return (
        <AuthenticatedLayout>
            <Head title="Tape Chart" />

            <div className="px-6 py-6">
                <div className="mb-6 flex items-end gap-4">
                    <h1 className="text-2xl font-semibold text-slate-800">Tape Chart</h1>
                    <div className="ml-auto flex gap-3">
                        <label className="flex flex-col text-xs text-slate-600">
                            From
                            <input
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                                className="mt-0.5 rounded border border-slate-300 px-2 py-1 text-sm"
                            />
                        </label>
                        <label className="flex flex-col text-xs text-slate-600">
                            To
                            <input
                                type="date"
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                                className="mt-0.5 rounded border border-slate-300 px-2 py-1 text-sm"
                            />
                        </label>
                    </div>
                </div>

                {error && (
                    <div className="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                        {error}
                    </div>
                )}

                {loading ? (
                    <div className="text-sm text-slate-500">Loading availability…</div>
                ) : (
                    <div className="overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
                        <table className="min-w-full border-collapse text-xs">
                            <thead className="sticky top-0 z-10 bg-slate-50">
                                <tr>
                                    <th className="sticky left-0 z-20 border-b border-r border-slate-200 bg-slate-50 px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wide text-slate-600">
                                        Room Type
                                    </th>
                                    {dates.map((d) => (
                                        <th
                                            key={d}
                                            className="border-b border-slate-200 px-2 py-2 text-center text-[11px] font-medium text-slate-600"
                                        >
                                            <div>{new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' })}</div>
                                            <div className="text-[10px] text-slate-400">
                                                {new Date(d).toLocaleDateString('en-IN', { weekday: 'short' })}
                                            </div>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {roomTypeIds.map((rtId) => (
                                    <tr key={rtId} className="hover:bg-slate-50">
                                        <td className="sticky left-0 border-b border-r border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700">
                                            Type #{rtId}
                                        </td>
                                        {dates.map((d) => {
                                            const cell = matrix[d]?.[rtId];
                                            return (
                                                <td
                                                    key={`${rtId}-${d}`}
                                                    className={`border-b border-slate-100 px-2 py-2 text-center ${cellClass(cell)}`}
                                                    title={cell ? `${cell.available}/${cell.total} available` : 'No data'}
                                                >
                                                    {cell ? cell.available : '—'}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="mt-4 flex flex-wrap gap-4 text-xs text-slate-600">
                    <span className="flex items-center gap-1.5">
                        <span className="inline-block h-3 w-3 rounded bg-emerald-100 border border-emerald-200" />
                        Plenty available
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="inline-block h-3 w-3 rounded bg-amber-200" />
                        Low
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="inline-block h-3 w-3 rounded bg-red-500" />
                        Sold out
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="inline-block h-3 w-3 rounded bg-red-700" />
                        Stop sell
                    </span>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
