import 'package:flutter/material.dart';
import '../../models/yle_models.dart';
import '../../services/yle_service.dart';
import '../../utils/error_utils.dart';

class YleStudentConfirmScreen extends StatefulWidget {
  final int submissionId;
  final int classId;
  final String? ocrRawName;
  final List<Map<String, dynamic>> candidates;
  final List<YleAnswerItem> answers;

  const YleStudentConfirmScreen({
    super.key,
    required this.submissionId,
    required this.classId,
    this.ocrRawName,
    required this.candidates,
    required this.answers,
  });

  @override
  State<YleStudentConfirmScreen> createState() => _YleStudentConfirmScreenState();
}

class _YleStudentConfirmScreenState extends State<YleStudentConfirmScreen> {
  final _service = YleService();
  final _nameController = TextEditingController();
  bool _saving = false;
  int? _selectedCandidateId;
  bool _showNewNameField = false;

  @override
  void initState() {
    super.initState();
    if (widget.candidates.isNotEmpty) {
      _selectedCandidateId = widget.candidates.first['studentId'] as int?;
    } else {
      _showNewNameField = true;
      _nameController.text = widget.ocrRawName ?? '';
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    super.dispose();
  }

  Future<void> _confirm() async {
    setState(() => _saving = true);

    try {
      if (_selectedCandidateId != null) {
        await _service.updateStudent(
          submissionId: widget.submissionId,
          studentId: _selectedCandidateId,
        );
      } else {
        final name = _nameController.text.trim();
        if (name.isEmpty) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Vui lòng nhập tên học sinh')),
            );
          }
          setState(() => _saving = false);
          return;
        }
        await _service.updateStudent(
          submissionId: widget.submissionId,
          createNewStudent: true,
          newStudentName: name,
        );
      }

      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() => _saving = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(friendlyError(e))),
        );
      }
    }
  }

  void _skip() {
    Navigator.pop(context, true);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Xác nhận học sinh'),
        centerTitle: true,
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // OCR Raw Name
          if (widget.ocrRawName != null) ...[
            Card(
              color: theme.colorScheme.primaryContainer,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'AI đọc được tên:',
                      style: theme.textTheme.titleSmall?.copyWith(
                        color: theme.colorScheme.onPrimaryContainer,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      widget.ocrRawName!,
                      style: theme.textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: theme.colorScheme.onPrimaryContainer,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
          ],

          // Candidates
          if (widget.candidates.isNotEmpty) ...[
            Text(
              'Chọn học sinh phù hợp:',
              style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            ...widget.candidates.map((c) {
              final studentId = c['studentId'] as int?;
              final fullName = c['fullName'] as String? ?? '';
              final similarity = (c['similarity'] as num?)?.toDouble() ?? 0.0;
              final selected = _selectedCandidateId == studentId;

              return Card(
                color: selected ? theme.colorScheme.primaryContainer : null,
                child: ListTile(
                  leading: CircleAvatar(
                    backgroundColor: selected
                        ? theme.colorScheme.primary
                        : theme.colorScheme.surfaceContainerHighest,
                    child: Text(
                      fullName.isNotEmpty ? fullName[0].toUpperCase() : '?',
                      style: TextStyle(
                        color: selected ? theme.colorScheme.onPrimary : null,
                      ),
                    ),
                  ),
                  title: Text(fullName),
                  subtitle: Text('${(similarity * 100).toStringAsFixed(0)}% trùng khớp'),
                  trailing: selected
                      ? Icon(Icons.check_circle, color: theme.colorScheme.primary)
                      : null,
                  onTap: () {
                    setState(() {
                      _selectedCandidateId = studentId;
                      _showNewNameField = false;
                    });
                  },
                ),
              );
            }),
            const SizedBox(height: 8),
            TextButton.icon(
              onPressed: () {
                setState(() {
                  _selectedCandidateId = null;
                  _showNewNameField = true;
                  _nameController.text = widget.ocrRawName ?? '';
                });
              },
              icon: const Icon(Icons.edit),
              label: const Text('Nhập tên khác'),
            ),
          ],

          // New name field
          if (_showNewNameField) ...[
            const SizedBox(height: 8),
            TextField(
              controller: _nameController,
              decoration: const InputDecoration(
                labelText: 'Tên học sinh',
                border: OutlineInputBorder(),
              ),
              textCapitalization: TextCapitalization.words,
            ),
          ],

          const SizedBox(height: 24),

          // AI answers for review
          if (widget.answers.isNotEmpty) ...[
            Text(
              'Đáp án AI đọc được từ trang 1:',
              style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            ...widget.answers.map((a) => Card(
              margin: const EdgeInsets.only(bottom: 4),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                child: Row(
                  children: [
                    SizedBox(
                      width: 60,
                      child: Text(
                        'Part ${a.partNumber}',
                        style: const TextStyle(fontWeight: FontWeight.w500, fontSize: 12),
                      ),
                    ),
                    Text(
                      'Câu ${a.questionNumber}: ',
                      style: const TextStyle(fontSize: 12),
                    ),
                    Expanded(
                      child: Text(
                        a.value,
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                    ),
                    if (a.confidence > 0)
                      Text(
                        '${(a.confidence * 100).toStringAsFixed(0)}%',
                        style: TextStyle(
                          fontSize: 11,
                          color: a.confidence >= 0.6 ? Colors.green : Colors.orange,
                        ),
                      ),
                  ],
                ),
              ),
            )),
            const SizedBox(height: 24),
          ],

          // Action buttons
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: _saving ? null : _skip,
                  child: const Text('Bỏ qua'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 2,
                child: ElevatedButton.icon(
                  onPressed: _saving ? null : _confirm,
                  icon: _saving
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.check),
                  label: Text(_saving ? 'Đang lưu...' : 'Xác nhận'),
                  style: ElevatedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
