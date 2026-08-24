/**
 * Extract output column names from a SQL SELECT query (for report definition).
 */
export function extractColumnsFromQuery(query) {
  if (!query) return [];
  try {
    const normalizedQuery = query.replace(/\s+/g, ' ').trim();
    const rankedOuterMatch = normalizedQuery.match(
      /^SELECT\s+(.*?)\s+FROM\s+\(\s*SELECT[\s\S]+?\)\s*(?:\w+)?\s+WHERE\s+rn\s*=\s*1\b/i
    );
    const selectMatch =
      rankedOuterMatch ?? normalizedQuery.match(/SELECT\s+(.*?)\s+FROM\s+/i);
    if (!selectMatch) return [];
    const columnsPart = selectMatch[1];
    const columns = [];
    let currentColumn = '';
    let parenCount = 0;
    for (let i = 0; i < columnsPart.length; i++) {
      const char = columnsPart[i];
      if (char === '(') parenCount++;
      else if (char === ')') parenCount--;
      if (char === ',' && parenCount === 0) {
        columns.push(currentColumn.trim());
        currentColumn = '';
      } else {
        currentColumn += char;
      }
    }
    if (currentColumn.trim()) columns.push(currentColumn.trim());
    const patterns = [
      /\s+AS\s+col_(\w+)\s*$/i,
      /\s+as\s+col_(\w+)\s*$/i,
      /\s+AS\s+col_(\w+)$/i,
      /\s+as\s+col_(\w+)$/i,
      /AS\s+col_(\w+)\s*$/i,
      /as\s+col_(\w+)\s*$/i,
    ];
    return columns
      .map((col) => {
        for (const pattern of patterns) {
          const match = col.match(pattern);
          if (match) return match[1];
        }
        const aliasMatch = col.match(/\s+AS\s+([\w]+)\s*$/i);
        if (aliasMatch) return aliasMatch[1];
        const trimmed = col.trim();
        if (!trimmed.includes('(') && !trimmed.includes('||')) {
          const dotted = trimmed.match(/(?:^|\s)(?:[\w]+\.)?([\w]+)\s*$/);
          if (dotted) return dotted[1];
        }
        return null;
      })
      .filter(Boolean)
      .filter((col) => col.toLowerCase() !== 'rn')
      .map((col) => {
        let type = 'string';
        let description = 'Text value';
        if (col.toLowerCase().includes('date') || col.toLowerCase().includes('at')) {
          type = 'date';
          description = 'Date value';
        } else if (/amount|total|sum|number/.test(col.toLowerCase())) {
          type = 'decimal';
          description = 'Numeric value';
        } else if (/id|count/.test(col.toLowerCase())) {
          type = 'integer';
          description = 'Numeric identifier';
        }
        return { name: col, type, description };
      });
  } catch (error) {
    console.error('Error extracting columns:', error);
    return [];
  }
}

export function normalizeStoredOutputColumns(columns) {
  return (columns || [])
    .map((col) => {
      if (typeof col === 'string') {
        return { name: col, type: 'string', description: '' };
      }
      return {
        name: col?.name ?? col?.key ?? col?.title,
        type: col?.type || 'string',
        description: col?.description || '',
      };
    })
    .filter((col) => col.name);
}

/** Prefer saved output_columns; fall back to SQL extraction for legacy reports. */
export function resolveReportOutputColumns(savedColumns, query) {
  const normalized = normalizeStoredOutputColumns(savedColumns).filter(
    (col) => String(col.name).toLowerCase() !== 'rn'
  );
  if (normalized.length > 0) {
    return normalized;
  }
  return extractColumnsFromQuery(query);
}

/**
 * Extract parameter names from a SQL query (e.g. :param_name).
 */
export function extractParamsFromQuery(query) {
  if (!query) return [];
  try {
    const paramMatches = query.match(/:\w+/g) || [];
    return [...new Set(paramMatches)].map((param) => {
      const name = param.substring(1);
      let type = 'string';
      let description = 'Text parameter';
      let required = true;
      let input;

      if (['from_date', 'to_date', 'shift_date', 'counter_date'].includes(name)) {
        type = 'date';
        input = 'date';
        description = 'Date filter';
      } else if (['shift_id', 'lane_id', 'user_id', 'body_type_id', 'year'].includes(name)) {
        type = 'integer';
        description = 'Numeric identifier';
      } else if (name === 'trans_type') {
        input = 'trans_type';
        description = 'Transaction type filter';
        required = false;
      } else if (name === 'account_no') {
        description = 'Account number filter';
      } else if (name === 'body_type' || name === 'lane' || name === 'operator' || name === 'collection_type') {
        description = `${name.replace(/_/g, ' ')} filter`;
      } else if (name.toLowerCase().includes('date')) {
        type = 'date';
        input = 'date';
        description = 'Date parameter';
      } else if (name.toLowerCase().includes('id')) {
        type = 'integer';
        description = 'Identifier parameter';
      } else if (/amount|total/.test(name.toLowerCase())) {
        type = 'decimal';
        description = 'Numeric parameter';
      }

      const entry = { name, type, required, description };
      if (input) entry.input = input;
      return entry;
    });
  } catch (error) {
    console.error('Error extracting parameters:', error);
    return [];
  }
}

export function humanizeColumnTitle(name) {
  if (!name) return '';
  return String(name)
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());
}
