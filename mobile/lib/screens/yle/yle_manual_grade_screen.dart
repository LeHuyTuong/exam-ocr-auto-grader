import 'package:flutter/material.dart';
import '../../models/yle_models.dart';
import '../../services/yle_service.dart';
import '../../utils/error_utils.dart';
import 'yle_result_screen.dart';

class YleManualGradeScreen extends StatefulWidget {
  final YleExam exam;
  final int submissionId;
  final int classId;

  const YleManualGradeScreen({
    super.key,
    required this.exam,
    required this.submissionId,
    required this.classId,
  });

  @override
  State<YleManualGradeScreen> createState() => _YleManualGradeScreenState();
}

class _YleManualGradeScreenState extends State<YleManualGradeScreen> {
  final _service = YleService();
  final Map<int, int> _manualMarks = {};
  bool _loading = true;
  bool _saving = false;
  YlePageResult? _result;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      _result = await _service.getSubmissionResult(widget.submissionId);

      for (final part in widget.exam.parts) {
        if (!part.isAutoGradable) {
          _manualMarks[part.id] = 0;
        }
      }
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  List<YlePart> get _manualParts =>
      widget.exam.parts.where((p) => !p.isAutoGradable).toList();

  String? _imageUrlForPart(YlePart part) {
    final page = _result?.pages.where((p) => p.pageNumber == part.pageNumber).firstOrNull;
    return page?.imageUrl;
  }

  Future<void> _save() async {
    setState(() => _saving = true);

    try {
      final marks = _manualMarks.entries
          .map((e) => {'part_id': e.key, 'marks': e.value})
          .toList();

      await _service.addManualMarks(
        submissionId: widget.submissionId,
        marks: marks,
      );

      if (mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (_) => YleResultScreen(
              submissionId: widget.submissionId,
              exam: widget.exam,
            ),
          ),
        );
      }
    } catch (e) {
      setState(() => _saving = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(friendlyError(e))),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Chấm tay'),
        centerTitle: true,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _manualParts.isEmpty
              ? const Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.check_circle, size: 64, color: Colors.green),
                      SizedBox(height: 16),
                      Text('Không có phần nào cần chấm tay'),
                    ],
                  ),
                )
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    Text(
                      'Xem ảnh bài làm và nhập số câu đúng',
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 24),
                    ..._manualParts.map((part) {
                      final imageUrl = _imageUrlForPart(part);
                      return Card(
                        margin: const EdgeInsets.only(bottom: 16),
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Part ${part.partNumber}: ${part.title}',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Tối đa: ${part.maxMarks} điểm',
                                style: TextStyle(color: theme.colorScheme.onSurfaceVariant),
                              ),
                              const SizedBox(height: 12),

                              // Student's uploaded image for this part
                              if (imageUrl != null) ...[
                                ClipRRect(
                                  borderRadius: BorderRadius.circular(8),
                                  child: Image.network(
                                    imageUrl,
                                    height: 200,
                                    width: double.infinity,
                                    fit: BoxFit.contain,
                                    loadingBuilder: (context, child, loadingProgress) {
                                      if (loadingProgress == null) return child;
                                      return SizedBox(
                                        height: 200,
                                        child: Center(
                                          child: CircularProgressIndicator(
                                            value: loadingProgress.expectedTotalBytes != null
                                                ? loadingProgress.cumulativeBytesLoaded /
                                                    loadingProgress.expectedTotalBytes!
                                                : null,
                                          ),
                                        ),
                                      );
                                    },
                                    errorBuilder: (context, error, stackTrace) {
                                      return Container(
                                        height: 200,
                                        color: Colors.grey[200],
                                        child: const Center(
                                          child: Column(
                                            mainAxisAlignment: MainAxisAlignment.center,
                                            children: [
                                              Icon(Icons.broken_image, size: 48, color: Colors.grey),
                                              SizedBox(height: 8),
                                              Text('Không thể tải ảnh'),
                                            ],
                                          ),
                                        ),
                                      );
                                    },
                                  ),
                                ),
                                const SizedBox(height: 12),
                              ],

                              // Marking slider
                              Row(
                                children: [
                                  const Text('Số câu đúng: '),
                                  IconButton(
                                    icon: const Icon(Icons.remove_circle_outline),
                                    onPressed: () {
                                      setState(() {
                                        if ((_manualMarks[part.id] ?? 0) > 0) {
                                          _manualMarks[part.id] = _manualMarks[part.id]! - 1;
                                        }
                                      });
                                    },
                                  ),
                                  Text(
                                    '${_manualMarks[part.id] ?? 0}',
                                    style: const TextStyle(
                                      fontSize: 24,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                  IconButton(
                                    icon: const Icon(Icons.add_circle_outline),
                                    onPressed: () {
                                      setState(() {
                                        if ((_manualMarks[part.id] ?? 0) < part.maxMarks) {
                                          _manualMarks[part.id] = _manualMarks[part.id]! + 1;
                                        }
                                      });
                                    },
                                  ),
                                ],
                              ),
                              Slider(
                                value: (_manualMarks[part.id] ?? 0).toDouble(),
                                min: 0,
                                max: part.maxMarks.toDouble(),
                                divisions: part.maxMarks,
                                label: '${_manualMarks[part.id] ?? 0}',
                                onChanged: (v) {
                                  setState(() => _manualMarks[part.id] = v.round());
                                },
                              ),
                            ],
                          ),
                        ),
                      );
                    }),
                    const SizedBox(height: 16),
                    ElevatedButton.icon(
                      onPressed: _saving ? null : _save,
                      icon: _saving
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.check),
                      label: Text(_saving ? 'Đang lưu...' : 'Lưu & Xem kết quả'),
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 16),
                      ),
                    ),
                  ],
                ),
    );
  }
}
