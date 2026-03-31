// Formatting utilities
// Known bugs: see GitHub issues

function formatCurrency(amount, currency) {
  return `${currency} ${amount.toFixed(2)}`;
}

function formatDate(dateStr) {
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) throw new Error("invalid date");
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function formatName(firstName, lastName) {
  if (!firstName || !lastName) throw new Error("name cannot be empty");
  return `${firstName.trim()} ${lastName.trim()}`;
}

function formatFileSize(bytes) {
  if (bytes < 0) throw new Error("bytes must be non-negative");
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(2)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

module.exports = { formatCurrency, formatDate, formatName, formatFileSize };
