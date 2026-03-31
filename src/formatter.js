// Formatting utilities
// Known bugs: see GitHub issues

function formatCurrency(amount, currency) {
  // BUG #8: ignores currency parameter — always uses USD
  return `$${amount.toFixed(2)}`;
}

function formatDate(dateStr) {
  // BUG #9: does not validate input — formatDate("not-a-date") returns "Invalid Date"
  const d = new Date(dateStr);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function formatName(firstName, lastName) {
  // BUG #10: crashes if firstName or lastName is null/undefined
  return `${firstName.trim()} ${lastName.trim()}`;
}

function formatFileSize(bytes) {
  // BUG #11: negative bytes returns "-1024.00 KB" instead of throwing
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(2)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

module.exports = { formatCurrency, formatDate, formatName, formatFileSize };
